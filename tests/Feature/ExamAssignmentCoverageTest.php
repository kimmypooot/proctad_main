<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\ExamAssignmentAttendance;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * REC/LEC/CE-for-Investigation assignments are stationed at exactly one
 * field office but reference multiple "covered schools" they monitor —
 * pre-determined (no confirmation workflow) but each still needs its own
 * attendance scan/manual entry, tracked separately from the field-office
 * attendance that drives certificate issuance.
 */
class ExamAssignmentCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** A venue for $examinationId sitting under a given testing center. */
    private function venueIn(int $examinationId, TestingCenter $center): ExaminationSchool
    {
        return ExaminationSchool::factory()->create([
            'examination_id' => $examinationId,
            'school_id' => School::factory()->create(['testing_center_id' => $center->id]),
        ]);
    }

    public function test_covered_schools_persist_for_a_coverage_role_on_store(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $covered1 = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $covered2 = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'rec_chair',
            'examination_school_id' => $testingCenter->id,
            'covered_school_ids' => [$covered1->id, $covered2->id],
        ])->assertRedirect();

        $assignment = ExamAssignment::firstOrFail();
        $this->assertSame($testingCenter->id, $assignment->examination_school_id);
        $this->assertEqualsCanonicalizing(
            [$covered1->id, $covered2->id],
            $assignment->coveredSchools->pluck('id')->all(),
        );
    }

    public function test_covered_school_ids_must_belong_to_the_examination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $foreignSchool = ExaminationSchool::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'rec_chair',
            'examination_school_id' => $testingCenter->id,
            'covered_school_ids' => [$foreignSchool->id],
        ])->assertSessionHasErrors('covered_school_ids.0');
    }

    public function test_non_coverage_role_ignores_covered_school_ids(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = \App\Models\FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = \App\Models\School::factory()->forFieldOffice($office->id)->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $member = Member::factory()->create(['field_office_id' => $office->id]);

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
            'covered_school_ids' => [$venue->id],
        ])->assertRedirect();

        $assignment = ExamAssignment::firstOrFail();
        $this->assertCount(0, $assignment->coveredSchools);
    }

    public function test_covered_schools_are_cleared_when_role_edited_away_from_coverage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = ExamAssignment::factory()->create(['role' => 'lec_chair']);
        $covered = ExaminationSchool::factory()->create(['examination_id' => $assignment->examination_id]);
        $assignment->coveredSchools()->sync([$covered->id]);

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'role' => 'proctor',
            'attended' => false,
        ])->assertRedirect();

        $this->assertCount(0, $assignment->fresh()->coveredSchools);
    }

    public function test_covered_schools_persist_through_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = ExamAssignment::factory()->create(['role' => 'lec_member']);
        $center = TestingCenter::factory()->create();
        $venue = $this->venueIn($assignment->examination_id, $center);
        $covered = $this->venueIn($assignment->examination_id, $center);

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'role' => 'lec_member',
            'attended' => false,
            'examination_school_id' => $venue->id,
            'covered_school_ids' => [$covered->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing([$covered->id], $assignment->fresh()->coveredSchools->pluck('id')->all());
    }

    /**
     * The REC/LEC split: REC monitors region-wide, LEC never leaves the testing
     * center it is seated at. Both are coverage roles, so before this rule the
     * two were indistinguishable and an LEC Chair could be handed a covered
     * school in another center entirely.
     */
    public function test_lec_covered_schools_must_stay_within_its_own_testing_center(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $center = TestingCenter::factory()->create();
        $otherCenter = TestingCenter::factory()->create();
        $venue = $this->venueIn($examination->id, $center);
        $outside = $this->venueIn($examination->id, $otherCenter);
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'lec_chair',
            'examination_school_id' => $venue->id,
            'covered_school_ids' => [$outside->id],
        ])->assertSessionHasErrors('covered_school_ids');

        $this->assertSame(0, ExamAssignment::count());
    }

    public function test_lec_may_cover_another_school_in_the_same_testing_center(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $center = TestingCenter::factory()->create();
        $venue = $this->venueIn($examination->id, $center);
        $sibling = $this->venueIn($examination->id, $center);
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'lec_member',
            'examination_school_id' => $venue->id,
            'covered_school_ids' => [$sibling->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$sibling->id],
            ExamAssignment::firstOrFail()->coveredSchools->pluck('id')->all(),
        );
    }

    public function test_rec_may_cover_a_school_in_another_testing_center(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $venue = $this->venueIn($examination->id, TestingCenter::factory()->create());
        $elsewhere = $this->venueIn($examination->id, TestingCenter::factory()->create());
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'rec_member',
            'examination_school_id' => $venue->id,
            'covered_school_ids' => [$elsewhere->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$elsewhere->id],
            ExamAssignment::firstOrFail()->coveredSchools->pluck('id')->all(),
        );
    }

    public function test_lec_covered_schools_cannot_be_set_before_a_venue_is_chosen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $covered = $this->venueIn($examination->id, TestingCenter::factory()->create());
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'lec_chair',
            'covered_school_ids' => [$covered->id],
        ])->assertSessionHasErrors('covered_school_ids');
    }

    public function test_scanning_at_testing_center_confirms_and_queues_certificate_as_usual(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'rec_chair',
            'examination_school_id' => $testingCenter->id,
        ]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}&examination_school_id={$testingCenter->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'confirmed'));

        $assignment->refresh();
        $this->assertNotNull($assignment->attendance_confirmed_at);
    }

    public function test_coverage_role_requires_a_venue_before_first_confirmation(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'lec_chair',
        ]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'venue_required'));
    }

    public function test_scanning_a_covered_school_records_separate_attendance_without_certificate_retrigger(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $coveredSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'lec_chair',
            'examination_school_id' => $testingCenter->id,
            'attendance_confirmed_at' => now(),
        ]);
        $assignment->coveredSchools()->sync([$coveredSchool->id]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}&examination_school_id={$coveredSchool->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'confirmed'));

        $this->assertDatabaseHas('exam_assignment_attendances', [
            'exam_assignment_id' => $assignment->id,
            'examination_school_id' => $coveredSchool->id,
        ]);

        // Rescanning the same covered school doesn't duplicate the row.
        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}&examination_school_id={$coveredSchool->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'already_confirmed'));

        $this->assertSame(1, ExamAssignmentAttendance::where('exam_assignment_id', $assignment->id)->count());
    }

    public function test_scanning_a_school_not_covered_by_the_assignment_reports_not_assigned(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $unrelatedSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'rec_member',
            'examination_school_id' => $testingCenter->id,
        ]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}&examination_school_id={$unrelatedSchool->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'not_assigned'));
    }

    public function test_covered_school_roster_appears_in_attendance_summary_when_venue_selected(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $coveredSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'lec_chair',
            'examination_school_id' => $testingCenter->id,
        ]);
        $assignment->coveredSchools()->sync([$coveredSchool->id]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?examination_id={$exam->id}&examination_school_id={$coveredSchool->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendanceSummary.roster', fn ($roster) => collect($roster)
                    ->pluck('value')
                    ->contains("covered:{$assignment->id}:{$coveredSchool->id}")));
    }

    public function test_bulk_manual_fallback_marks_covered_school_attendance(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $coveredSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'lec_chair',
            'examination_school_id' => $testingCenter->id,
        ]);
        $assignment->coveredSchools()->sync([$coveredSchool->id]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)->post('/scanner/mark-attendance', [
            'type' => 'exam',
            'examination_id' => $exam->id,
            'covered_attendance_ids' => ["{$assignment->id}:{$coveredSchool->id}"],
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_assignment_attendances', [
            'exam_assignment_id' => $assignment->id,
            'examination_school_id' => $coveredSchool->id,
            'scan_method' => 'manual',
        ]);

        // Idempotent: re-submitting the same pair doesn't duplicate or error.
        $this->actingAs($admin)->post('/scanner/mark-attendance', [
            'type' => 'exam',
            'examination_id' => $exam->id,
            'covered_attendance_ids' => ["{$assignment->id}:{$coveredSchool->id}"],
        ])->assertRedirect();

        $this->assertSame(1, ExamAssignmentAttendance::where('exam_assignment_id', $assignment->id)->count());
    }

    public function test_bulk_manual_fallback_rejects_a_school_not_covered_by_the_assignment(): void
    {
        $office = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $unrelatedSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'lec_chair',
            'examination_school_id' => $testingCenter->id,
        ]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)->post('/scanner/mark-attendance', [
            'type' => 'exam',
            'examination_id' => $exam->id,
            'covered_attendance_ids' => ["{$assignment->id}:{$unrelatedSchool->id}"],
        ])->assertRedirect();

        $this->assertDatabaseMissing('exam_assignment_attendances', [
            'exam_assignment_id' => $assignment->id,
            'examination_school_id' => $unrelatedSchool->id,
        ]);
    }

    public function test_bulk_manual_fallback_rejects_covered_attendance_for_assignment_in_another_field_office(): void
    {
        $leyte = \App\Models\FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $samar = \App\Models\FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $member = Member::factory()->create(['field_office_id' => $samar->id]);
        $exam = Examination::factory()->create();
        $testingCenter = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $coveredSchool = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $samar->id,
            'role' => 'lec_chair',
            'examination_school_id' => $testingCenter->id,
        ]);
        $assignment->coveredSchools()->sync([$coveredSchool->id]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte->id]);

        $this->actingAs($admin)->post('/scanner/mark-attendance', [
            'type' => 'exam',
            'examination_id' => $exam->id,
            'covered_attendance_ids' => ["{$assignment->id}:{$coveredSchool->id}"],
        ])->assertRedirect();

        $this->assertDatabaseMissing('exam_assignment_attendances', [
            'exam_assignment_id' => $assignment->id,
        ]);
    }
}
