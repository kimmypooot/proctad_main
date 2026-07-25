<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Field Office Staff and Field Directors who are also accredited members
 * serve where they work. The regional roles (Super Admin, ESD Admin, the two
 * directors) may be assigned anywhere in RO VIII, and ordinary members keep
 * the looser room-role rule.
 */
class StaffJurisdictionTest extends TestCase
{
    use RefreshDatabase;

    private Examination $examination;

    private FieldOffice $leyte;

    private FieldOffice $samar;

    private ExaminationSchool $leyteVenue;

    private ExaminationSchool $samarVenue;

    protected function setUp(): void
    {
        parent::setUp();

        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $this->examination = Examination::factory()->create([
            'exam_type_id' => $examType->id,
            'exam_date' => '2026-08-09',
        ]);

        $this->leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);

        $this->leyteVenue = $this->venue($this->leyte);
        $this->samarVenue = $this->venue($this->samar);
    }

    private function venue(FieldOffice $office): ExaminationSchool
    {
        $school = School::factory()->forFieldOffice($office->id)->create([
            'name' => "{$office->code} National High School",
            'is_active' => true,
        ]);

        return ExaminationSchool::create([
            'examination_id' => $this->examination->id,
            'school_id' => $school->id,
        ]);
    }

    /** An accredited member who also holds a staff post at $office. */
    private function staffMember(UserRole $role, ?FieldOffice $office): Member
    {
        $user = User::factory()->create(['role' => $role, 'field_office_id' => $office?->id, 'is_active' => true]);

        return Member::factory()->create([
            'user_id' => $user->id,
            'field_office_id' => $office?->id ?? $this->leyte->id,
            'status' => 'active',
        ]);
    }

    private function assign(Member $member, ExamRole $role, ?ExaminationSchool $venue)
    {
        return $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->post("/examinations/{$this->examination->id}/assignments", [
                'member_id' => $member->id,
                'role' => $role->value,
                'examination_school_id' => $venue?->id,
            ]);
    }

    public function test_testing_center_staff_may_serve_in_their_own_center(): void
    {
        $this->assign($this->staffMember(UserRole::FoAdmin, $this->leyte), ExamRole::Coordinator, $this->leyteVenue)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_testing_center_staff_may_not_serve_in_another_center(): void
    {
        $this->assign($this->staffMember(UserRole::FoAdmin, $this->leyte), ExamRole::Coordinator, $this->samarVenue)
            ->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('exam_assignments', 0);
    }

    /**
     * The rule reaches past the room roles: Coordinator and the committee seats
     * were previously unconstrained for everyone.
     */
    public function test_the_limit_covers_roles_the_room_rule_never_did(): void
    {
        $this->assign($this->staffMember(UserRole::FoAdmin, $this->leyte), ExamRole::LecChair, $this->samarVenue)
            ->assertSessionHasErrors('member_id');
    }

    public function test_field_directors_are_confined_the_same_way(): void
    {
        $director = $this->staffMember(UserRole::FieldDirector, $this->leyte);

        $this->assign($director, ExamRole::LecChair, $this->leyteVenue)
            ->assertSessionHasNoErrors();
    }

    public function test_field_directors_may_not_cross_into_another_center(): void
    {
        $this->assign($this->staffMember(UserRole::FieldDirector, $this->leyte), ExamRole::LecChair, $this->samarVenue)
            ->assertSessionHasErrors('member_id');
    }

    /** A venue-less seat is a regional one, which these staff cannot hold. */
    public function test_field_office_scoped_staff_need_a_venue(): void
    {
        $this->assign($this->staffMember(UserRole::FoAdmin, $this->leyte), ExamRole::RecMember, null)
            ->assertSessionHasErrors('member_id');
    }

    public function test_regional_staff_may_serve_anywhere(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::DirectorIv, UserRole::DirectorIii] as $role) {
            $this->assign($this->staffMember($role, null), ExamRole::RecMember, $this->samarVenue)
                ->assertSessionHasNoErrors();
        }
    }

    public function test_regional_staff_may_hold_a_venueless_seat(): void
    {
        $this->assign($this->staffMember(UserRole::EsdAdmin, null), ExamRole::RecMember, null)
            ->assertSessionHasNoErrors();
    }

    /** Ordinary members are untouched by this rule. */
    public function test_ordinary_members_keep_the_looser_rule(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id, 'status' => 'active']);

        // Coordinator is not a room role, so an out-of-office venue is fine.
        $this->assign($member, ExamRole::Coordinator, $this->samarVenue)
            ->assertSessionHasNoErrors();
    }

    public function test_a_bulk_assignment_is_checked_too(): void
    {
        $staff = $this->staffMember(UserRole::FoAdmin, $this->leyte);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->post("/examinations/{$this->examination->id}/assignments/bulk", [
                'member_ids' => [$staff->id],
                'role' => ExamRole::Coordinator->value,
                'examination_school_id' => $this->samarVenue->id,
            ])
            ->assertSessionHasErrors('member_ids');
    }

    /**
     * The staffing form needs to know who is confined so it can hide them once
     * a venue is chosen, rather than offering a choice the server will refuse.
     */
    public function test_the_staffing_form_marks_confined_members(): void
    {
        $confined = $this->staffMember(UserRole::FoAdmin, $this->leyte);
        $regional = $this->staffMember(UserRole::EsdAdmin, null);
        $ordinary = Member::factory()->create(['field_office_id' => $this->leyte->id, 'status' => 'active']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get("/examinations/{$this->examination->id}")
            ->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($confined, $regional, $ordinary) {
                $byId = collect($page->toArray()['props']['assignableMembers'])->keyBy('id');

                $this->assertSame(
                    [$this->leyteVenue->school->testing_center_id],
                    $byId[$confined->id]['confined_to_center_ids'],
                );
                $this->assertNull($byId[$regional->id]['confined_to_center_ids']);
                $this->assertNull($byId[$ordinary->id]['confined_to_center_ids']);
            });
    }

    /** The edit path can move a venue out of jurisdiction just as easily. */
    public function test_an_assignment_cannot_be_edited_across_centers(): void
    {
        $staff = $this->staffMember(UserRole::FoAdmin, $this->leyte);

        $this->assign($staff, ExamRole::Coordinator, $this->leyteVenue)->assertSessionHasNoErrors();
        $assignment = ExamAssignment::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->put("/assignments/{$assignment->id}", [
                'role' => ExamRole::Coordinator->value,
                'attended' => false,
                'examination_school_id' => $this->samarVenue->id,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame($this->leyteVenue->id, $assignment->fresh()->examination_school_id);
    }
}
