<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Hosting rotates between the two Leyte offices, so which of them receives new
 * registrations at Tacloban City has to be changeable without touching the
 * database. Registration asks applicants for a city and resolves the office
 * from this flag, so exactly one office must hold it at any time.
 */
class IntakeOfficeTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $leyteOne;

    private FieldOffice $leyteTwo;

    private TestingCenter $tacloban;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leyteOne = FieldOffice::factory()->create(['name' => 'CSC Field Office - Leyte I']);
        $this->leyteTwo = FieldOffice::factory()->create(['name' => 'CSC Field Office - Leyte II']);

        $this->tacloban = TestingCenter::factory()->create(['name' => 'Tacloban City']);
        $this->tacloban->fieldOffices()->attach($this->leyteOne->id, ['is_primary' => true]);
        $this->tacloban->fieldOffices()->attach($this->leyteTwo->id, ['is_primary' => false]);
    }

    private function primaryId(): ?int
    {
        return $this->tacloban->fresh()->primaryFieldOfficeId();
    }

    public function test_a_region_wide_admin_hands_intake_to_the_other_office(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $this->leyteTwo->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->leyteTwo->id, $this->primaryId());
    }

    /** Exactly one office may hold intake, or registration resolves ambiguously. */
    public function test_designating_an_office_clears_the_previous_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        $primaries = $this->tacloban->fieldOffices()
            ->wherePivot('is_primary', true)
            ->pluck('field_offices.id');

        $this->assertSame([$this->leyteTwo->id], $primaries->all());
    }

    /** The point of the whole feature: the next registrant lands elsewhere. */
    public function test_flipping_intake_redirects_the_next_registration(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        // Registration is a guest flow — drop the admin session first.
        $this->post('/logout');

        $this->withSession(['google_pending_registration' => [
            'google_id' => 'g-1', 'email' => 'juan@example.test',
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'avatar' => null,
        ]])->post('/register', [
            'first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz', 'suffix' => '',
            'sex' => 'male', 'date_of_birth' => '1990-05-15', 'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office', 'position' => 'Teacher III',
            'testing_center_id' => $this->tacloban->id, 'terms' => true,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(
            $this->leyteTwo->id,
            Member::where('email', 'juan@example.test')->value('field_office_id'),
        );
    }

    /**
     * The two offices are peers, so neither may take the other's registrants.
     * Top management decides who hosts.
     */
    public function test_field_office_staff_cannot_change_the_intake_office(): void
    {
        foreach ([UserRole::FoAdmin, UserRole::FieldDirector] as $role) {
            $this->actingAs(User::factory()->create([
                'role' => $role,
                'field_office_id' => $this->leyteTwo->id,
            ]))
                ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                    'field_office_id' => $this->leyteTwo->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame($this->leyteOne->id, $this->primaryId());
    }

    /** Intake cannot be handed to an office that does not serve the center. */
    public function test_an_office_that_does_not_handle_the_center_is_rejected(): void
    {
        $samar = FieldOffice::factory()->create(['name' => 'CSC Field Office - Samar']);

        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $samar->id,
            ])
            ->assertSessionHasErrors('field_office_id');

        $this->assertSame($this->leyteOne->id, $this->primaryId());
    }

    /** Who receives registrations is exactly what the audit trail is for. */
    public function test_the_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($admin)
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        $log = AuditLog::where('auditable_type', TestingCenter::class)
            ->where('auditable_id', $this->tacloban->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($this->leyteOne->id, $log->changes['old']['primary_field_office_id']);
        $this->assertSame($this->leyteTwo->id, $log->changes['new']['primary_field_office_id']);
    }

    /** Re-designating the office that already holds intake is a no-op, not an audit entry. */
    public function test_redesignating_the_same_office_records_nothing(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/primary-office", [
                'field_office_id' => $this->leyteOne->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->leyteOne->id, $this->primaryId());
        $this->assertSame(0, AuditLog::where('auditable_type', TestingCenter::class)
            ->where('auditable_id', $this->tacloban->id)
            ->where('action', 'updated')
            ->count());
    }

    /** The Locations page exposes intake and gates the control by role. */
    public function test_the_locations_page_exposes_intake_and_gates_the_control(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('testingCenters.0.primary_field_office_id', $this->leyteOne->id)
                ->where('testingCenters.0.can_designate_primary', true)
                ->count('testingCenters.0.handling_offices', 2));

        $this->actingAs(User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->leyteOne->id,
        ]))
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                // Visible, so staff know where registrants go — but not editable.
                ->where('testingCenters.0.primary_field_office_id', $this->leyteOne->id)
                ->where('testingCenters.0.can_designate_primary', false));
    }
}
