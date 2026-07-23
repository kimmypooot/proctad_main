<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exam-day cover: a test administrator who does not report is marked absent,
 * and an Alternate Examiner from the venue's standby pool takes the seat.
 */
class AlternateExaminerTest extends TestCase
{
    use RefreshDatabase;

    private Examination $examination;

    private ExaminationSchool $venue;

    private ExamRoom $room;

    private FieldOffice $office;

    protected function setUp(): void
    {
        parent::setUp();

        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $this->examination = Examination::factory()->create([
            'exam_type_id' => $examType->id,
            'exam_date' => '2026-08-09',
        ]);

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $school = School::factory()->create(['field_office_id' => $this->office->id, 'is_active' => true]);
        $this->venue = ExaminationSchool::create([
            'examination_id' => $this->examination->id,
            'school_id' => $school->id,
        ]);
        $this->room = ExamRoom::create([
            'examination_school_id' => $this->venue->id,
            'room_number' => '101',
            'capacity' => 25,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    private function assignment(ExamRole $role, ?ExamRoom $room = null, ?ExaminationSchool $venue = null): ExamAssignment
    {
        return ExamAssignment::create([
            'examination_id' => $this->examination->id,
            'examination_school_id' => ($venue ?? $this->venue)->id,
            'exam_room_id' => $room?->id,
            'member_id' => Member::factory()->create(['field_office_id' => $this->office->id, 'status' => 'active'])->id,
            'role' => $role,
            'field_office_id' => $this->office->id,
            'status' => AssignmentStatus::Confirmed,
        ]);
    }

    public function test_an_assignee_can_be_marked_absent(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);

        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/absent")
            ->assertRedirect();

        $this->assertNotNull($proctor->fresh()->marked_absent_at);
    }

    /**
     * The distinction the whole feature rests on: absent is not the same as
     * not-yet-scanned, and someone already scanned in is present by definition.
     */
    public function test_someone_who_already_reported_cannot_be_marked_absent(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $proctor->update(['attendance_confirmed_at' => now()]);

        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/absent")
            ->assertSessionHas('error');

        $this->assertNull($proctor->fresh()->marked_absent_at);
    }

    public function test_an_alternate_takes_over_the_seat_role_and_room(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $alternate->refresh();

        $this->assertSame(ExamRole::Proctor, $alternate->role);
        $this->assertSame($this->room->id, $alternate->exam_room_id);
        $this->assertSame($proctor->id, $alternate->covering_for_assignment_id);
        $this->assertSame(ExamRole::AlternateExaminer, $alternate->original_role);
        // Stepping in is itself the report-in — otherwise the room reads as
        // unstaffed for the whole examination.
        $this->assertNotNull($alternate->attendance_confirmed_at);
    }

    /**
     * The point of rewriting the role: the certificate prints it, and
     * evaluability is decided by it.
     */
    public function test_the_substitute_becomes_evaluable_as_the_role_served(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->assertFalse($alternate->role->isEvaluable());

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id]);

        $this->assertTrue($alternate->fresh()->role->isEvaluable());
    }

    public function test_an_alternate_cannot_be_called_in_before_the_seat_is_vacant(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id])
            ->assertSessionHas('error');

        $this->assertNull($alternate->fresh()->covering_for_assignment_id);
    }

    public function test_only_an_alternate_examiner_can_cover(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $other = $this->assignment(ExamRole::RoomExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $other->id])
            ->assertSessionHas('error');
    }

    /** The standby pool is per venue — an alternate covers where they stand. */
    public function test_an_alternate_cannot_cover_another_venue(): void
    {
        $otherSchool = School::factory()->create(['field_office_id' => $this->office->id, 'is_active' => true]);
        $otherVenue = ExaminationSchool::create([
            'examination_id' => $this->examination->id,
            'school_id' => $otherSchool->id,
        ]);

        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer, null, $otherVenue);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id])
            ->assertSessionHas('error');
    }

    public function test_a_seat_cannot_be_covered_twice(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $first = $this->assignment(ExamRole::AlternateExaminer);
        $second = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $first->id]);
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $second->id])
            ->assertSessionHas('error');

        $this->assertNull($second->fresh()->covering_for_assignment_id);
    }

    public function test_an_alternate_cannot_cover_two_seats(): void
    {
        $first = $this->assignment(ExamRole::Proctor, $this->room);
        $second = $this->assignment(ExamRole::RoomExaminer, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$first->id}/absent");
        $this->actingAs($this->admin())->post("/assignments/{$second->id}/absent");

        $this->actingAs($this->admin())
            ->post("/assignments/{$first->id}/alternate", ['alternate_assignment_id' => $alternate->id]);
        $this->actingAs($this->admin())
            ->post("/assignments/{$second->id}/alternate", ['alternate_assignment_id' => $alternate->id])
            ->assertSessionHas('error');

        $this->assertSame($first->id, $alternate->fresh()->covering_for_assignment_id);
    }

    public function test_standing_down_returns_the_alternate_to_the_pool(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id]);

        $this->actingAs($this->admin())
            ->delete("/assignments/{$alternate->id}/alternate")
            ->assertSessionHas('success');

        $alternate->refresh();

        $this->assertSame(ExamRole::AlternateExaminer, $alternate->role);
        $this->assertNull($alternate->covering_for_assignment_id);
        $this->assertNull($alternate->exam_room_id);
    }

    /** Clearing an absence under a covering alternate would double-staff the room. */
    public function test_an_absence_cannot_be_cleared_while_covered(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);
        $alternate = $this->assignment(ExamRole::AlternateExaminer);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->post("/assignments/{$proctor->id}/alternate", ['alternate_assignment_id' => $alternate->id]);

        $this->actingAs($this->admin())
            ->delete("/assignments/{$proctor->id}/absent")
            ->assertSessionHas('error');

        $this->assertNotNull($proctor->fresh()->marked_absent_at);
    }

    public function test_an_absence_can_be_cleared_when_no_one_is_covering(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);

        $this->actingAs($this->admin())->post("/assignments/{$proctor->id}/absent");
        $this->actingAs($this->admin())
            ->delete("/assignments/{$proctor->id}/absent")
            ->assertSessionHas('success');

        $this->assertNull($proctor->fresh()->marked_absent_at);
    }

    public function test_members_cannot_mark_anyone_absent(): void
    {
        $proctor = $this->assignment(ExamRole::Proctor, $this->room);

        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->post("/assignments/{$proctor->id}/absent")
            ->assertForbidden();
    }
}
