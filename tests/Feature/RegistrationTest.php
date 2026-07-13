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

    private function validPayload(FieldOffice $fieldOffice, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => '',
            'sex' => 'male',
            'email' => 'juan@example.test',
            'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office',
            'position' => 'Teacher III',
            'field_office_id' => $fieldOffice->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => true,
        ], $overrides);
    }

    public function test_registration_creates_a_fully_usable_member_immediately(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $this->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'juan@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertAuthenticatedAs($user);

        $member = Member::where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->proctad_id);
        $this->assertSame($fieldOffice->id, $member->field_office_id);
        $this->assertSame('male', $member->sex->value ?? $member->sex);
        $this->assertTrue($member->requirements()->exists());
    }

    public function test_registration_requires_sex_agency_and_testing_center(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $this->post('/register', $this->validPayload($fieldOffice, [
            'sex' => '',
            'agency' => '',
            'field_office_id' => '',
        ]))->assertSessionHasErrors(['sex', 'agency', 'field_office_id']);

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

        $this->post('/register', $this->validPayload($fieldOffice))
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

        $this->post('/register', $this->validPayload($fieldOffice))
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

        $this->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
