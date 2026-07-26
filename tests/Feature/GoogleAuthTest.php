<?php

namespace Tests\Feature;

use App\Models\FieldOffice;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $id, string $email, ?string $givenName = null, ?string $familyName = null): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = $id;
        $googleUser->email = $email;
        $googleUser->avatar = 'https://example.test/avatar.jpg';
        $googleUser->user = ['given_name' => $givenName, 'family_name' => $familyName];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_known_email_links_google_id_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'member@proctad.test']);
        $this->mockGoogleUser('google-123', 'member@proctad.test');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    public function test_returning_google_user_matches_by_google_id(): void
    {
        $user = User::factory()->create(['email' => 'member@proctad.test']);
        $user->forceFill(['google_id' => 'google-123'])->save();

        $this->mockGoogleUser('google-123', 'different-email@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_redirects_to_registration_completion(): void
    {
        $this->mockGoogleUser('google-999', 'stranger@gmail.com', 'Stranger', 'Person');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('register'));

        $this->assertGuest();
        $this->assertSame(0, User::where('google_id', 'google-999')->count());
        $this->assertSame('stranger@gmail.com', session('google_pending_registration.email'));
    }

    public function test_register_page_prefills_pending_google_identity(): void
    {
        $this->mockGoogleUser('google-999', 'stranger@gmail.com', 'Stranger', 'Person');
        $this->get('/auth/google/callback');

        $this->get('/register')
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('google.email', 'stranger@gmail.com')
                ->where('google.first_name', 'Stranger')
                ->where('google.last_name', 'Person'));
    }

    public function test_completes_registration_from_pending_google_identity(): void
    {
        $center = TestingCenter::factory()->forFieldOffice(FieldOffice::factory()->create())->create();
        $this->mockGoogleUser('google-999', 'stranger@gmail.com', 'Stranger', 'Person');
        $this->get('/auth/google/callback');

        $this->post('/register', [
            'first_name' => 'Stranger',
            'last_name' => 'Person',
            'sex' => 'male',
            'date_of_birth' => '1990-05-15',
            'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office',
            'testing_center_id' => $center->id,
            'terms' => true,
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'stranger@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-999', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('google_pending_registration'));

        $member = \App\Models\Member::where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->proctad_id);
    }

    public function test_registration_rejects_a_pending_google_identity_whose_email_was_since_taken(): void
    {
        $center = TestingCenter::factory()->forFieldOffice(FieldOffice::factory()->create())->create();
        $this->mockGoogleUser('google-999', 'stranger@gmail.com', 'Stranger', 'Person');
        $this->get('/auth/google/callback');

        User::factory()->create(['email' => 'stranger@gmail.com']);

        $this->post('/register', [
            'first_name' => 'Stranger',
            'last_name' => 'Person',
            'sex' => 'male',
            'date_of_birth' => '1990-05-15',
            'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office',
            'testing_center_id' => $center->id,
            'terms' => true,
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_member_login_page_renders(): void
    {
        $this->get('/member/login')->assertOk();
    }

    public function test_locked_account_cannot_login_via_google(): void
    {
        $user = User::factory()->create(['email' => 'member@proctad.test']);
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->mockGoogleUser('google-123', 'member@proctad.test');

        $this->get('/auth/google/callback')->assertRedirect(route('member.login'));
        $this->assertGuest();
    }

    public function test_google_login_updates_last_login_and_audits(): void
    {
        $user = User::factory()->create(['email' => 'member@proctad.test']);
        $this->mockGoogleUser('google-123', 'member@proctad.test');

        $this->get('/auth/google/callback');

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(
            \App\Models\AuditLog::where('user_id', $user->id)
                ->where('action', 'login')
                ->where('changes->method', 'google')
                ->exists(),
        );
    }
}
