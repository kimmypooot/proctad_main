<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExamType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Regional Examination Committee is chaired ex officio by the Director IV
 * and co-chaired by the Director III, so those two seats are not staffed from
 * the member pool — see ExamRole::reservedForRole.
 */
class ReservedExamSeatTest extends TestCase
{
    use RefreshDatabase;

    private Examination $examination;

    protected function setUp(): void
    {
        parent::setUp();

        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $this->examination = Examination::factory()->create([
            'title' => 'August 2026 CSE-PPT',
            'exam_type_id' => $examType->id,
            'exam_date' => '2026-08-09',
        ]);
    }

    /** An accredited member who also holds a staff post. */
    private function officer(UserRole $role): Member
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        return Member::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    }

    private function assign(Member $member, ExamRole $role)
    {
        return $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->post("/examinations/{$this->examination->id}/assignments", [
                'member_id' => $member->id,
                'role' => $role->value,
            ]);
    }

    public function test_the_director_iv_may_take_the_rec_chair(): void
    {
        $director = $this->officer(UserRole::DirectorIv);

        $this->assign($director, ExamRole::RecChair)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_assignments', [
            'member_id' => $director->id,
            'role' => ExamRole::RecChair->value,
        ]);
    }

    public function test_an_ordinary_member_cannot_take_the_rec_chair(): void
    {
        $this->officer(UserRole::DirectorIv);
        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->assign($ordinary, ExamRole::RecChair)
            ->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('exam_assignments', 0);
    }

    public function test_the_director_iii_cannot_take_the_chair_reserved_for_the_director_iv(): void
    {
        $this->officer(UserRole::DirectorIv);
        $directorIii = $this->officer(UserRole::DirectorIii);

        $this->assign($directorIii, ExamRole::RecChair)
            ->assertSessionHasErrors('member_id');
    }

    public function test_the_director_iii_takes_the_co_chair(): void
    {
        $directorIii = $this->officer(UserRole::DirectorIii);

        $this->assign($directorIii, ExamRole::RecCoChair)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /**
     * The rule must not be able to strand the committee. With the post vacant
     * or its holder not yet enrolled, the seat falls open to the pool rather
     * than becoming unfillable.
     */
    public function test_the_seat_falls_open_when_no_director_is_enrolled(): void
    {
        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->assign($ordinary, ExamRole::RecChair)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_a_deactivated_director_does_not_hold_the_seat(): void
    {
        $user = User::factory()->create(['role' => UserRole::DirectorIv, 'is_active' => false]);
        Member::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->assign($ordinary, ExamRole::RecChair)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_unreserved_roles_are_open_to_anyone(): void
    {
        $this->officer(UserRole::DirectorIv);
        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->assign($ordinary, ExamRole::Proctor)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /**
     * The edit path is the back door: without the same check an admin could
     * assign someone as a Proctor and then promote them into the chair.
     */
    public function test_an_assignment_cannot_be_edited_into_a_reserved_seat(): void
    {
        $this->officer(UserRole::DirectorIv);
        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->assign($ordinary, ExamRole::Proctor)->assertSessionHasNoErrors();
        $assignment = ExamAssignment::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->put("/assignments/{$assignment->id}", [
                'role' => ExamRole::RecChair->value,
                'attended' => false,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(ExamRole::Proctor, $assignment->fresh()->role);
    }

    public function test_a_bulk_assignment_cannot_fill_a_reserved_seat(): void
    {
        $this->officer(UserRole::DirectorIv);
        $ordinary = Member::factory()->create(['status' => 'active']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->post("/examinations/{$this->examination->id}/assignments/bulk", [
                'member_ids' => [$ordinary->id],
                'role' => ExamRole::RecChair->value,
            ])
            // Reported on the array, not member_ids.0 — that is the key the
            // staffing form actually renders.
            ->assertSessionHasErrors('member_ids');
    }

    /** The staffing form pre-selects the holder so admins aren't left guessing. */
    public function test_the_staffing_form_names_the_reserved_holder(): void
    {
        $director = $this->officer(UserRole::DirectorIv);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get("/examinations/{$this->examination->id}")
            ->assertInertia(function (Assert $page) use ($director) {
                $roles = collect($page->toArray()['props']['roles']);

                $this->assertSame(
                    $director->id,
                    $roles->firstWhere('value', ExamRole::RecChair->value)['reserved_member_id'],
                );
                $this->assertNull(
                    $roles->firstWhere('value', ExamRole::Proctor->value)['reserved_member_id'],
                );
            });
    }
}
