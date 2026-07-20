<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\Member;
use App\Models\User;
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

    public function test_guests_still_get_the_public_form_with_no_shortcut(): void
    {
        $this->get('/evaluation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Evaluations/Create')
                ->has('examinations')
                ->has('myAssignments', 0));
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
