<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Leyte I and Leyte II are separate offices with separate staff that jointly
 * serve one testing center, taking turns hosting. Jurisdiction therefore runs
 * through the testing center, not the office: either office's staff must see
 * and manage the whole Tacloban City roster, while an office that shares no
 * center with them stays out of reach.
 *
 * Members of the regional office (RO8) sit outside that scheme entirely —
 * they serve region-wide, so every office can see and assign them, but none
 * owns their record.
 */
class SharedJurisdictionTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $leyteOne;

    private FieldOffice $leyteTwo;

    private FieldOffice $samar;

    private FieldOffice $regional;

    private TestingCenter $tacloban;

    private TestingCenter $catbalogan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leyteOne = FieldOffice::create(['name' => 'CSC Field Office - Leyte I', 'code' => 'FOLI-TAC']);
        $this->leyteTwo = FieldOffice::create(['name' => 'CSC Field Office - Leyte II', 'code' => 'FOLII-TAC']);
        $this->samar = FieldOffice::create(['name' => 'CSC Field Office - Samar', 'code' => 'FOS']);
        $this->regional = FieldOffice::create([
            'name' => 'CSC Regional Office VIII', 'code' => 'RO8', 'is_regional' => true,
        ]);

        // The one shared center: both Leyte offices handle Tacloban City.
        $this->tacloban = TestingCenter::factory()->create(['name' => 'Tacloban City']);
        $this->tacloban->fieldOffices()->attach($this->leyteTwo->id, ['is_primary' => true]);
        $this->tacloban->fieldOffices()->attach($this->leyteOne->id, ['is_primary' => false]);

        $this->catbalogan = TestingCenter::factory()->forFieldOffice($this->samar)->create(['name' => 'Catbalogan City']);
    }

    private function staff(UserRole $role, ?FieldOffice $office): User
    {
        return User::factory()->create(['role' => $role, 'field_office_id' => $office?->id]);
    }

    private function memberAt(FieldOffice $office, ?TestingCenter $center): Member
    {
        return Member::factory()->create([
            'field_office_id' => $office->id,
            'testing_center_id' => $center?->id,
            'status' => MemberStatus::Active,
        ]);
    }

    /** The case the whole change exists for. */
    public function test_leyte_one_staff_can_see_and_manage_a_member_registered_under_leyte_two(): void
    {
        $member = $this->memberAt($this->leyteTwo, $this->tacloban);
        $admin = $this->staff(UserRole::FoAdmin, $this->leyteOne);

        $this->assertTrue($admin->can('view', $member));
        $this->assertTrue($admin->can('update', $member));

        $this->actingAs($admin)
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.id', $member->id));
    }

    /** …and symmetrically, since neither office is subordinate to the other. */
    public function test_leyte_two_staff_can_manage_a_member_registered_under_leyte_one(): void
    {
        $member = $this->memberAt($this->leyteOne, $this->tacloban);

        $this->assertTrue($this->staff(UserRole::FieldDirector, $this->leyteTwo)->can('update', $member));
    }

    /** Sharing a center is what grants access — not merely being a field office. */
    public function test_an_office_sharing_no_center_stays_out_of_reach(): void
    {
        $member = $this->memberAt($this->leyteTwo, $this->tacloban);
        $samarAdmin = $this->staff(UserRole::FoAdmin, $this->samar);

        $this->assertFalse($samarAdmin->can('view', $member));
        $this->assertFalse($samarAdmin->can('update', $member));

        $this->actingAs($samarAdmin)
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    }

    /** Requirement 4: RO8 members are assignable region-wide, so every office sees them. */
    public function test_a_regional_office_member_is_visible_to_every_field_office(): void
    {
        $regionalMember = $this->memberAt($this->regional, null);

        foreach ([$this->leyteOne, $this->leyteTwo, $this->samar] as $office) {
            $this->assertTrue(
                $this->staff(UserRole::FoAdmin, $office)->can('view', $regionalMember),
                "{$office->code} should see a regional-office member",
            );
        }
    }

    /** Visible to all, owned by none — no field office may edit their record. */
    public function test_a_regional_office_member_cannot_be_edited_by_field_office_staff(): void
    {
        $regionalMember = $this->memberAt($this->regional, null);

        $this->assertFalse($this->staff(UserRole::FoAdmin, $this->leyteOne)->can('update', $regionalMember));
        $this->assertFalse($this->staff(UserRole::FieldDirector, $this->leyteOne)->can('update', $regionalMember));
        $this->assertFalse($this->staff(UserRole::FoAdmin, $this->samar)->can('delete', $regionalMember));

        // Region-wide roles still own them.
        $this->assertTrue($this->staff(UserRole::EsdAdmin, null)->can('update', $regionalMember));
    }

    /** A regional member may staff any venue; a Tacloban member may not staff Catbalogan. */
    public function test_venue_jurisdiction_admits_regional_members_anywhere_but_confines_the_rest(): void
    {
        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $examination = Examination::factory()->create(['exam_type_id' => $examType->id, 'exam_date' => '2026-08-09']);

        $catbaloganVenue = ExaminationSchool::create([
            'examination_id' => $examination->id,
            'school_id' => School::factory()->create(['testing_center_id' => $this->catbalogan->id])->id,
        ]);

        $regionalMember = $this->memberAt($this->regional, null);
        $taclobanMember = $this->memberAt($this->leyteOne, $this->tacloban);
        $admin = $this->staff(UserRole::EsdAdmin, null);

        $this->actingAs($admin)
            ->post("/examinations/{$examination->id}/assignments", [
                'member_id' => $regionalMember->id,
                'role' => ExamRole::Proctor->value,
                'examination_school_id' => $catbaloganVenue->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post("/examinations/{$examination->id}/assignments", [
                'member_id' => $taclobanMember->id,
                'role' => ExamRole::Proctor->value,
                'examination_school_id' => $catbaloganVenue->id,
            ])
            ->assertSessionHasErrors('examination_school_id');
    }

    /** A Tacloban venue draws on both Leyte rosters, since both offices serve it. */
    public function test_a_leyte_one_admin_may_assign_a_leyte_two_member_at_the_shared_center(): void
    {
        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $examination = Examination::factory()->create(['exam_type_id' => $examType->id, 'exam_date' => '2026-08-09']);

        $taclobanVenue = ExaminationSchool::create([
            'examination_id' => $examination->id,
            'school_id' => School::factory()->create(['testing_center_id' => $this->tacloban->id])->id,
        ]);

        $member = $this->memberAt($this->leyteTwo, $this->tacloban);

        $this->actingAs($this->staff(UserRole::FoAdmin, $this->leyteOne))
            ->post("/examinations/{$examination->id}/assignments", [
                'member_id' => $member->id,
                'role' => ExamRole::Proctor->value,
                'examination_school_id' => $taclobanVenue->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_assignments', [
            'member_id' => $member->id,
            'examination_school_id' => $taclobanVenue->id,
        ]);
    }

    /**
     * Members left unplaced by the backfill (their office handles several
     * centers, so none could be derived) belong to nobody until staff choose
     * one — better than defaulting them into an office that may not serve them.
     */
    public function test_a_member_with_no_testing_center_is_not_managed_by_anyone(): void
    {
        $unplaced = $this->memberAt($this->samar, null);

        $this->assertFalse($this->staff(UserRole::FoAdmin, $this->samar)->can('view', $unplaced));
        $this->assertFalse($this->staff(UserRole::FoAdmin, $this->samar)->can('update', $unplaced));
        $this->assertTrue($this->staff(UserRole::EsdAdmin, null)->can('update', $unplaced));
    }

    /** The members list flags them so the gap is visible rather than silent. */
    public function test_the_members_list_flags_a_member_needing_a_testing_center(): void
    {
        $unplaced = $this->memberAt($this->samar, null);
        $placed = $this->memberAt($this->samar, $this->catbalogan);
        $regionalMember = $this->memberAt($this->regional, null);

        $this->actingAs($this->staff(UserRole::EsdAdmin, null))
            ->get('/members')
            ->assertInertia(function (Assert $page) use ($unplaced, $placed, $regionalMember) {
                $flags = collect($page->toArray()['props']['members']['data'])
                    ->pluck('needs_testing_center', 'id');

                $this->assertTrue($flags[$unplaced->id]);
                $this->assertFalse($flags[$placed->id]);
                // Regional members legitimately have none — never flagged.
                $this->assertFalse($flags[$regionalMember->id]);
            });
    }

    /**
     * The edit form must offer the centers of the whole shared jurisdiction, so
     * a Leyte I admin can place a member at Tacloban regardless of which office
     * the record is filed under.
     */
    public function test_the_edit_form_offers_the_jurisdictions_offices_and_centers(): void
    {
        $member = $this->memberAt($this->leyteTwo, $this->tacloban);

        $response = $this->actingAs($this->staff(UserRole::FoAdmin, $this->leyteOne))
            ->getJson("/members/{$member->id}/edit-data")
            ->assertOk();

        $this->assertSame($this->tacloban->id, $response->json('member.testing_center_id'));
        $this->assertSame([$this->tacloban->id], $response->json('testingCenters.*.id'));
        $this->assertEqualsCanonicalizing(
            [$this->leyteOne->id, $this->leyteTwo->id],
            $response->json('fieldOffices.*.id'),
        );

        // The center carries its handling offices so the form can narrow itself.
        $this->assertEqualsCanonicalizing(
            [$this->leyteOne->id, $this->leyteTwo->id],
            $response->json('testingCenters.0.field_office_ids'),
        );
    }

    /** An admin outside the jurisdiction cannot even load the edit form. */
    public function test_the_edit_form_is_refused_outside_the_jurisdiction(): void
    {
        $member = $this->memberAt($this->leyteTwo, $this->tacloban);

        $this->actingAs($this->staff(UserRole::FoAdmin, $this->samar))
            ->getJson("/members/{$member->id}/edit-data")
            ->assertForbidden();
    }

    /** Records stamped with a sibling office are visible across the jurisdiction. */
    public function test_records_stamped_to_the_sibling_office_are_visible(): void
    {
        $leyteOneStaff = $this->staff(UserRole::FoAdmin, $this->leyteOne);

        $this->assertEqualsCanonicalizing(
            [$this->leyteOne->id, $this->leyteTwo->id],
            $leyteOneStaff->scopedFieldOfficeIds(),
        );

        $this->assertSame([$this->tacloban->id], $leyteOneStaff->scopedTestingCenterIds());
        $this->assertSame([$this->catbalogan->id], $this->staff(UserRole::FoAdmin, $this->samar)->scopedTestingCenterIds());
    }
}
