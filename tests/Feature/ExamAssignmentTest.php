<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $leyte;

    private FieldOffice $samar;

    private Examination $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $this->exam = Examination::factory()->create();
    }

    public function test_fo_admin_assigns_only_own_fo_members(): void
    {
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $this->leyte->id]);
        $ownMember = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $otherMember = Member::factory()->create(['field_office_id' => $this->samar->id]);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments", [
                'member_id' => $ownMember->id,
                'role' => ExamRole::RoomExaminer->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_assignments', [
            'examination_id' => $this->exam->id,
            'member_id' => $ownMember->id,
            'field_office_id' => $this->leyte->id,
        ]);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments", [
                'member_id' => $otherMember->id,
                'role' => ExamRole::Proctor->value,
            ])
            ->assertForbidden();
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);

        ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'member_id' => $member->id,
            'field_office_id' => $this->leyte->id,
        ]);

        $this->actingAs($esd)
            ->post("/examinations/{$this->exam->id}/assignments", [
                'member_id' => $member->id,
                'role' => ExamRole::Proctor->value,
            ])
            ->assertSessionHasErrors('member_id');
    }

    public function test_update_sets_rating_and_manual_attendance_and_writes_audit(): void
    {
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $this->leyte->id])->id,
            'field_office_id' => $this->leyte->id,
            'role' => ExamRole::Proctor,
        ]);

        $this->actingAs($esd)
            ->put("/assignments/{$assignment->id}", [
                'role' => ExamRole::RoomExaminer->value,
                'performance_rating' => PerformanceRating::Outstanding->value,
                'remarks' => 'Excellent handling of the room.',
                'attended' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame(ExamRole::RoomExaminer, $assignment->role);
        $this->assertSame(PerformanceRating::Outstanding, $assignment->performance_rating);
        $this->assertNotNull($assignment->attendance_confirmed_at);
        $this->assertSame($esd->id, $assignment->attendance_confirmed_by);

        $this->assertTrue(AuditLog::where('auditable_type', ExamAssignment::class)
            ->where('auditable_id', $assignment->id)
            ->where('action', 'updated')
            ->where('user_id', $esd->id)
            ->exists());
    }

    public function test_fo_admin_cannot_modify_other_fo_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $this->leyte->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $this->samar->id])->id,
            'field_office_id' => $this->samar->id,
        ]);

        $this->actingAs($admin)
            ->put("/assignments/{$assignment->id}", [
                'role' => ExamRole::Proctor->value,
                'attended' => true,
            ])
            ->assertForbidden();

        $this->actingAs($admin)->delete("/assignments/{$assignment->id}")->assertForbidden();
    }

    public function test_bulk_assign_deploys_multiple_members_and_skips_already_assigned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $alreadyAssigned = Member::factory()->create();
        ExamAssignment::factory()->create(['examination_id' => $this->exam->id, 'member_id' => $alreadyAssigned->id]);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments/bulk", [
                'member_ids' => [$a->id, $b->id, $alreadyAssigned->id],
                'role' => ExamRole::Proctor->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, ExamAssignment::where('examination_id', $this->exam->id)->count());
        $this->assertDatabaseHas('exam_assignments', ['examination_id' => $this->exam->id, 'member_id' => $a->id]);
        $this->assertDatabaseHas('exam_assignments', ['examination_id' => $this->exam->id, 'member_id' => $b->id]);
    }

    public function test_bulk_confirm_only_confirms_pending_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $pending = ExamAssignment::factory()->create(['examination_id' => $this->exam->id, 'status' => 'pending']);
        $declined = ExamAssignment::factory()->create(['examination_id' => $this->exam->id, 'status' => 'declined']);

        $this->actingAs($admin)
            ->post('/assignments/bulk-confirm', ['assignment_ids' => [$pending->id, $declined->id]])
            ->assertRedirect();

        $this->assertSame('confirmed', $pending->fresh()->status->value);
        $this->assertSame('declined', $declined->fresh()->status->value);
    }

    public function test_assign_room_updates_only_the_room_link(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = \App\Models\ExaminationSchool::factory()->create(['examination_id' => $this->exam->id]);
        $room = \App\Models\ExamRoom::factory()->create(['examination_school_id' => $venue->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'examination_school_id' => $venue->id,
            'role' => ExamRole::Proctor->value,
        ]);

        $this->actingAs($admin)
            ->patch("/assignments/{$assignment->id}/room", ['exam_room_id' => $room->id])
            ->assertRedirect();

        $this->assertSame($room->id, $assignment->fresh()->exam_room_id);

        $this->actingAs($admin)
            ->patch("/assignments/{$assignment->id}/room", ['exam_room_id' => null])
            ->assertRedirect();

        $this->assertNull($assignment->fresh()->exam_room_id);
    }
}
