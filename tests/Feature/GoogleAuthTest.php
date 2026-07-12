<?php

namespace Tests\Feature;

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

    private function mockGoogleUser(string $id, string $email): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = $id;
        $googleUser->email = $email;

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

    public function test_unknown_email_is_rejected(): void
    {
        $this->mockGoogleUser('google-999', 'stranger@gmail.com');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('member.login'));

        $this->assertGuest();
        $this->assertSame(0, User::where('google_id', 'google-999')->count());
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
