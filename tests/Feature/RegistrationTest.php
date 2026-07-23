<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
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

    /** Self-registration (Google sign-up) links the new user to their field office's centers. */
    public function test_registration_links_the_new_user_to_their_testing_centers(): void
    {
        $fieldOffice = FieldOffice::factory()->create();
        $centerA = TestingCenter::factory()->forFieldOffice($fieldOffice)->create();
        $centerB = TestingCenter::factory()->forFieldOffice($fieldOffice)->create();

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'juan@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$centerA->id, $centerB->id],
            $user->testingCenters()->pluck('testing_centers.id')->all(),
        );
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

    /**
     * The same person re-registering under a different email — the case that
     * matters most now members are advised off agency Google accounts, since
     * that changes their email but not who they are.
     *
     * The mobile number differs deliberately: dual-SIM is the norm in the
     * Philippines, so the same person routinely holds two numbers and a phone
     * match would let this straight through.
     */
    public function test_registration_blocks_a_duplicate_name_and_date_of_birth_despite_a_different_number(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'date_of_birth' => '1990-05-15',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09179999999',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    /** Common surnames are ordinary here; a shared name alone must not block. */
    public function test_registration_allows_a_namesake_with_a_different_date_of_birth(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'date_of_birth' => '1975-01-02',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09179999999',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    /**
     * Regression against the previous mobile-number rule, which compared first
     * and last name without suffix: a "Jr." sharing a household phone with his
     * father was falsely blocked. Their birth dates differ, so this is allowed.
     */
    public function test_registration_allows_a_junior_sharing_a_household_number(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'suffix' => null,
            'date_of_birth' => '1962-03-08',
            'email' => 'father@example.test',
            'mobile_number' => '09171234567',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice, ['suffix' => 'Jr.']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    /** PROCTAD IDs are permanently reserved, so a removed member still counts. */
    public function test_registration_blocks_a_duplicate_of_a_soft_deleted_member(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        $member = Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'date_of_birth' => '1990-05-15',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09179999999',
            'field_office_id' => $fieldOffice->id,
        ]);
        $member->delete();

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Prevention and detection must agree on what "same name" means. */
    public function test_registration_normalises_whitespace_when_matching(): void
    {
        $fieldOffice = FieldOffice::factory()->create();

        Member::factory()->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'date_of_birth' => '1990-05-15',
            'email' => 'someone-else@example.test',
            'mobile_number' => '09179999999',
            'field_office_id' => $fieldOffice->id,
        ]);

        $this->withGooglePending()
            ->post('/register', $this->validPayload($fieldOffice, [
                'first_name' => '  Juan  ',
                'last_name' => ' Dela Cruz ',
            ]))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
