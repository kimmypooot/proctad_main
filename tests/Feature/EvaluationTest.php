<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\Member;
use App\Models\User;
use App\Support\EvaluationCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the signed-in shortcut on the evaluation form. The page itself is
 * public and must stay usable with no login at all — it is filled in on or just
 * after exam day — so the guest path is asserted alongside.
 */
class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function attendedAssignment(Member $member, ExamRole $role = ExamRole::Proctor): ExamAssignment
    {
        return ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'role' => $role,
            'attendance_confirmed_at' => now(),
            'examination_id' => Examination::factory()->create(['exam_date' => now()->subDays(3)])->id,
        ]);
    }

    /**
     * A venue with a supervising examiner and one room examiner, all attendance
     * confirmed, so the SE has somebody it is legitimate to rate.
     *
     * @return array{0: ExamAssignment, 1: ExamAssignment}
     */
    private function venueWithSupervisorAndRatee(): array
    {
        $examination = Examination::factory()->create(['exam_date' => now()->subDays(3)]);
        $venue = \App\Models\ExaminationSchool::factory()->create(['examination_id' => $examination->id]);

        $make = fn (ExamRole $role) => ExamAssignment::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'role' => $role,
            'attendance_confirmed_at' => now(),
            'examination_id' => $examination->id,
            'examination_school_id' => $venue->id,
        ]);

        return [$make(ExamRole::SupervisingExaminer), $make(ExamRole::RoomExaminer)];
    }

    /** Sizes come from the criteria constants so the payload cannot drift from the rules. */
    private function supervisorPayload(ExamAssignment $supervisor, array $roomRating): array
    {
        $scores = fn (array $criteria) => array_fill(0, count($criteria), 4);

        return [
            'examination_id' => $supervisor->examination_id,
            'exam_assignment_id' => $supervisor->id,
            'room_ratings' => [$roomRating + [
                'punctuality' => $scores(EvaluationCriteria::PUNCTUALITY),
                'decorum' => $scores(EvaluationCriteria::DECORUM),
                'procedures' => $scores(EvaluationCriteria::PROCEDURES),
            ]],
            'room_readiness' => array_fill(0, count(EvaluationCriteria::ROOM_READINESS), true),
            'venue_readiness' => $scores(EvaluationCriteria::VENUE_READINESS),
            'committee_coordination' => $scores(EvaluationCriteria::COMMITTEE_COORDINATION),
            'conduct_of_exam' => $scores(EvaluationCriteria::CONDUCT_OF_EXAM),
            'examinee_experience' => $scores(EvaluationCriteria::EXAMINEE_EXPERIENCE),
            'overall_rating' => 4,
        ];
    }

    /** The ratee list is what the picker offers, and what submission is checked against. */
    public function test_resolve_offers_the_venues_room_examiners_and_proctors(): void
    {
        [$supervisor, $ratee] = $this->venueWithSupervisorAndRatee();

        $this->getJson("/evaluation/assignments/{$supervisor->id}")
            ->assertOk()
            ->assertJsonPath('available_ratees.0.exam_assignment_id', $ratee->id);
    }

    public function test_a_rating_is_stored_against_the_selected_assignment(): void
    {
        [$supervisor, $ratee] = $this->venueWithSupervisorAndRatee();

        $this->post('/evaluation', $this->supervisorPayload($supervisor, [
            'exam_assignment_id' => $ratee->id,
            'room_no' => '001',
            'ratee_name' => $ratee->member->name,
        ]))->assertRedirect();

        $stored = \App\Models\Evaluation::firstOrFail();
        $this->assertSame($ratee->id, $stored->room_ratings[0]['exam_assignment_id']);
    }

    /**
     * The whole point of the picker: a rating with no assignment id is matched by
     * PerformanceRatingCalculator against nobody, so it is silently lost. It used
     * to be accepted, because the name was free text and the id was nullable.
     */
    public function test_a_rating_without_an_assignment_id_is_rejected(): void
    {
        [$supervisor] = $this->venueWithSupervisorAndRatee();

        $this->post('/evaluation', $this->supervisorPayload($supervisor, [
            'exam_assignment_id' => null,
            'room_no' => '001',
            'ratee_name' => 'Someone Typed By Hand',
        ]))->assertSessionHasErrors('room_ratings.0.exam_assignment_id');

        $this->assertSame(0, \App\Models\Evaluation::count());
    }

    /** A respondent may only rate staff at the venue they actually served. */
    public function test_a_ratee_from_another_venue_is_rejected(): void
    {
        [$supervisor] = $this->venueWithSupervisorAndRatee();
        [, $elsewhere] = $this->venueWithSupervisorAndRatee();

        $this->post('/evaluation', $this->supervisorPayload($supervisor, [
            'exam_assignment_id' => $elsewhere->id,
            'room_no' => '001',
            'ratee_name' => $elsewhere->member->name,
        ]))->assertSessionHasErrors('room_ratings.0.exam_assignment_id');

        $this->assertSame(0, \App\Models\Evaluation::count());
    }

    public function test_guests_still_get_the_public_form_with_no_shortcut(): void
    {
        $this->get('/evaluation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Evaluations/Create')
                ->has('examinations')
                ->has('myAssignments', 0)
                // Drives the search flow: a guest must still be able to find
                // themselves by name on exam day.
                ->where('isMember', false));
    }

    /**
     * A member never searches for their own name — the system already knows who
     * they are and which examinations they attended. isMember is what suppresses
     * the examination picker and the search on the page.
     */
    public function test_a_member_is_marked_for_the_self_service_flow(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $this->attendedAssignment($member);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isMember', true)
                ->has('myAssignments', 1));
    }

    /** With nothing outstanding they are told so, rather than shown a search. */
    public function test_a_member_with_nothing_outstanding_is_still_self_service(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isMember', true)
                ->has('myAssignments', 0));
    }

    /** Staff with no member record of their own keep the anonymous search. */
    public function test_staff_without_a_member_record_keep_the_search(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::FoAdmin]))
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->where('isMember', false));
    }

    public function test_signed_in_member_is_offered_their_own_attended_assignments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $assignment = $this->attendedAssignment($member);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('myAssignments', 1)
                ->where('myAssignments.0.id', $assignment->id)
                ->where('myAssignments.0.examination_id', $assignment->examination_id));
    }

    /**
     * The same conditions resolve() enforces: an unconfirmed attendance cannot
     * be self-selected, so it must not be offered either.
     */
    public function test_assignments_without_confirmed_attendance_are_not_offered(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);

        ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'role' => ExamRole::Proctor,
            'attendance_confirmed_at' => null,
            'examination_id' => Examination::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 0));
    }

    /** Only the four designations the form covers can evaluate. */
    public function test_uncovered_designations_are_not_offered(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);

        $this->attendedAssignment($member, ExamRole::Driver);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 0));
    }

    /**
     * The member area must agree with the form about what is outstanding. All
     * three read ExamAssignment::scopeAwaitingEvaluationFor, so a member is
     * never told an evaluation is due on something the form would then reject.
     */
    public function test_service_history_flags_the_same_assignment_the_form_offers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $this->attendedAssignment($member);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('records.0.needs_evaluation', true));

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 1));
    }

    public function test_service_history_does_not_flag_an_uncovered_designation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $this->attendedAssignment($member, ExamRole::Driver);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertInertia(fn (Assert $page) => $page->where('records.0.needs_evaluation', false));
    }

    public function test_the_member_dashboard_counts_outstanding_evaluations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $this->attendedAssignment($member);
        $this->attendedAssignment($member, ExamRole::RoomExaminer);

        // Not evaluable, so it must not inflate the count.
        $this->attendedAssignment($member, ExamRole::Driver);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats', fn ($stats) => collect($stats)
                    ->firstWhere('label', 'Evaluations to Complete')['value'] === 2));
    }

    /**
     * The form is one page in two frames — a signed-in member keeps their
     * dashboard shell, a guest gets the public one. Asserted at the route level
     * since the layout swap itself is client-side: both must return the same
     * component, so no second page has crept in.
     */
    public function test_the_form_is_one_page_for_both_guests_and_members(): void
    {
        $this->get('/evaluation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Evaluations/Create'));

        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Evaluations/Create'));
    }

    /**
     * Monitoring and the member's own view must agree. They previously did not:
     * monitoring filtered on role and examination only, so an assignment whose
     * attendance was never confirmed showed to staff as "Not Submitted" while
     * the member was correctly told there was nothing to evaluate — and the form
     * would have refused the submission anyway. Staff chased people who could
     * not comply.
     */
    public function test_monitoring_ignores_assignments_the_form_would_refuse(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);

        $examination = Examination::factory()->create(['exam_date' => now()->subDays(3)]);

        // Assigned and confirmed, but never marked present.
        ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'role' => ExamRole::SupervisingExaminer,
            'attendance_confirmed_at' => null,
            'examination_id' => $examination->id,
        ]);

        $this->actingAs($admin)
            ->get("/evaluation-monitoring?examination_id={$examination->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 0)
                ->where('summary.not_submitted', 0));

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 0));
    }

    /** Once attendance is confirmed, both views agree it is outstanding. */
    public function test_monitoring_and_the_member_agree_once_attendance_is_confirmed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);

        $examination = Examination::factory()->create(['exam_date' => now()->subDays(3)]);

        ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'role' => ExamRole::SupervisingExaminer,
            'attendance_confirmed_at' => now(),
            'examination_id' => $examination->id,
        ]);

        $this->actingAs($admin)
            ->get("/evaluation-monitoring?examination_id={$examination->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 1)
                ->where('summary.not_submitted', 1));

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 1));
    }

    /**
     * Roles list in seniority order, not alphabetically. Worth pinning because
     * the ordering was MySQL's FIELD() and is now a portable CASE — the whole
     * point being that this page can finally be exercised under test at all.
     */
    public function test_monitoring_lists_roles_in_seniority_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create(['exam_date' => now()->subDays(3)]);
        $office = \App\Models\FieldOffice::factory()->create();

        // Created deliberately out of order.
        foreach ([ExamRole::Proctor, ExamRole::ChiefExaminer, ExamRole::RoomExaminer, ExamRole::SupervisingExaminer] as $role) {
            ExamAssignment::factory()->create([
                'member_id' => Member::factory()->create()->id,
                'role' => $role,
                'attendance_confirmed_at' => now(),
                'examination_id' => $examination->id,
                'field_office_id' => $office->id,
            ]);
        }

        $this->actingAs($admin)
            ->get("/evaluation-monitoring?examination_id={$examination->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignments.data.0.role', ExamRole::ChiefExaminer->value)
                ->where('assignments.data.1.role', ExamRole::SupervisingExaminer->value)
                ->where('assignments.data.2.role', ExamRole::Proctor->value)
                ->where('assignments.data.3.role', ExamRole::RoomExaminer->value));
    }

    /** A member never sees somebody else's assignment in their own shortcut. */
    public function test_shortcut_is_scoped_to_the_signed_in_member(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->attendedAssignment(Member::factory()->create());

        $this->actingAs($user)
            ->get('/evaluation')
            ->assertInertia(fn (Assert $page) => $page->has('myAssignments', 0));
    }
}
