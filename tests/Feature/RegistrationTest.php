<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function googlePending(array $overrides = []): array
    {
        return array_merge([
            'google_id' => 'google-123',
            'email' => 'juan@example.test',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'avatar' => 'https://example.test/avatar.png',
        ], $overrides);
    }

    private function withGooglePending(array $overrides = []): self
    {
        $this->withSession(['google_pending_registration' => $this->googlePending($overrides)]);

        return $this;
    }

    private function validPayload(FieldOffice $fieldOffice, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => '',
            'sex' => 'male',
            'date_of_birth' => '1990-05-15',
            'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office',
            'position' => 'Teacher III',
            'field_office_id' => $fieldOffice->id,
            'terms' => true,
        ], $overrides);
    }

    public function test_registration_creates_a_fully_usable_member_immediately(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'juan@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertNotNull($user->google_id);
        $this->assertAuthenticatedAs($user);

        $member = Member::where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->proctad_id);
        $this->assertSame($fieldOffice->id, $member->field_office_id);
        $this->assertSame('male', $member->sex->value ?? $member->sex);
        $this->assertSame('1990-05-15', $member->date_of_birth);
        $this->assertTrue($member->requirements()->exists());
    }

    public function test_registration_requires_connecting_google_first(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $this->post('/register', $this->validPayload($fieldOffice))
            ->assertForbidden();

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_requires_sex_agency_date_of_birth_and_testing_center(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice, [
                'sex' => '',
                'agency' => '',
                'date_of_birth' => '',
                'field_office_id' => '',
            ]))->assertSessionHasErrors(['sex', 'agency', 'date_of_birth', 'field_office_id']);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_blocks_a_duplicate_matching_email_mobile_and_name(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'email' => 'juan@example.test',
            'mobile_number' => '09171234567',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_blocks_a_duplicate_matching_only_mobile_and_name(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        // Different email, but the same mobile number and name — still a
        // confident-enough signal that this is the same person re-registering.
        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09171234567',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_allows_a_partial_match_that_is_not_a_true_duplicate(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        // Same name only — a different email AND a different mobile number.
        // Common names alone shouldn't block a legitimately different person.
        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09179999999',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
