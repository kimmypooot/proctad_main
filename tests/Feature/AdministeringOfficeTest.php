<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\Signatory;
use App\Models\TestingCenter;
use App\Models\User;
use App\Support\MemberIdCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Test administrators belong to a testing center, not a field office, so their
 * ID cards and certificates are signed by whichever office administers that
 * center. Tacloban City is served by both Leyte offices, so one of them holds
 * it — and which one must be changeable without touching the database, since
 * administration rotates.
 */
class AdministeringOfficeTest extends TestCase
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
        return $this->tacloban->fresh()->administeringFieldOfficeId();
    }

    public function test_a_region_wide_admin_hands_administration_to_the_other_office(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $this->leyteTwo->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->leyteTwo->id, $this->primaryId());
    }

    /** Exactly one office may hold it, or the signatory is ambiguous. */
    public function test_designating_an_office_clears_the_previous_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        $primaries = $this->tacloban->fieldOffices()
            ->wherePivot('is_primary', true)
            ->pluck('field_offices.id');

        $this->assertSame([$this->leyteTwo->id], $primaries->all());
    }

    /**
     * The point of the whole feature. An external test administrator has no
     * field office of their own, so the signature on their ID card follows the
     * office administering their center — and moves when that does.
     */
    public function test_the_administering_offices_signatory_signs_for_the_center(): void
    {
        $member = Member::factory()->create([
            'field_office_id' => null,
            'testing_center_id' => $this->tacloban->id,
        ]);

        Signatory::create([
            'field_office_id' => $this->leyteOne->id,
            'name' => 'DIR. ALPHA', 'position' => 'Director II', 'is_active' => true,
        ]);
        Signatory::create([
            'field_office_id' => $this->leyteTwo->id,
            'name' => 'DIR. BRAVO', 'position' => 'Director II', 'is_active' => true,
        ]);

        $this->assertSame('DIR. ALPHA', MemberIdCard::data($member)['signatory']['name']);

        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        $this->assertSame('DIR. BRAVO', MemberIdCard::data($member->fresh())['signatory']['name']);
    }

    /** Registration files nobody under a field office — the column is for staff. */
    public function test_registration_leaves_the_field_office_empty(): void
    {
        $this->withSession(['google_pending_registration' => [
            'google_id' => 'g-1', 'email' => 'juan@example.test',
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'avatar' => null,
        ]])->post('/register', [
            'first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz', 'suffix' => '',
            'sex' => 'male', 'date_of_birth' => '1990-05-15', 'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office', 'position' => 'Teacher III',
            'testing_center_id' => $this->tacloban->id, 'terms' => true,
        ])->assertRedirect(route('dashboard'));

        $member = Member::where('email', 'juan@example.test')->firstOrFail();

        $this->assertNull($member->field_office_id);
        $this->assertNull($member->user->field_office_id);
        $this->assertSame($this->tacloban->id, $member->testing_center_id);
    }

    /**
     * The two offices are peers, so neither may claim the center and put its
     * own signature on the other's members' certificates.
     */
    public function test_field_office_staff_cannot_change_the_administering_office(): void
    {
        foreach ([UserRole::FoAdmin, UserRole::FieldDirector] as $role) {
            $this->actingAs(User::factory()->create([
                'role' => $role,
                'field_office_id' => $this->leyteTwo->id,
            ]))
                ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                    'field_office_id' => $this->leyteTwo->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame($this->leyteOne->id, $this->primaryId());
    }

    /** Administration cannot be handed to an office that does not serve the center. */
    public function test_an_office_that_does_not_handle_the_center_is_rejected(): void
    {
        $samar = FieldOffice::factory()->create(['name' => 'CSC Field Office - Samar']);

        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $samar->id,
            ])
            ->assertSessionHasErrors('field_office_id');

        $this->assertSame($this->leyteOne->id, $this->primaryId());
    }

    /** Whose signature appears on a certificate is what the audit trail is for. */
    public function test_the_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($admin)
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $this->leyteTwo->id,
            ]);

        $log = AuditLog::where('auditable_type', TestingCenter::class)
            ->where('auditable_id', $this->tacloban->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($this->leyteOne->id, $log->changes['old']['administering_field_office_id']);
        $this->assertSame($this->leyteTwo->id, $log->changes['new']['administering_field_office_id']);
    }

    /** Re-designating the office that already holds it is a no-op, not an audit entry. */
    public function test_redesignating_the_same_office_records_nothing(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->patch("/testing-centers/{$this->tacloban->id}/administering-office", [
                'field_office_id' => $this->leyteOne->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->leyteOne->id, $this->primaryId());
        $this->assertSame(0, AuditLog::where('auditable_type', TestingCenter::class)
            ->where('auditable_id', $this->tacloban->id)
            ->where('action', 'updated')
            ->count());
    }

    /** The Locations page exposes the administering office and gates the control. */
    public function test_the_locations_page_exposes_the_administering_office(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::EsdAdmin]))
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('testingCenters.0.administering_field_office_id', $this->leyteOne->id)
                ->where('testingCenters.0.can_designate_administering', true)
                ->count('testingCenters.0.handling_offices', 2));

        $this->actingAs(User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->leyteOne->id,
        ]))
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                // Visible, so staff know where registrants go — but not editable.
                ->where('testingCenters.0.administering_field_office_id', $this->leyteOne->id)
                ->where('testingCenters.0.can_designate_administering', false));
    }
}
