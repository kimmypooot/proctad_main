<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAssignmentOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_reassign_changes_role_and_venue_preserving_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();
        $exam = Examination::factory()->create(['exam_date' => now()->addWeek()]);
        $member = \App\Models\Member::factory()->create(['field_office_id' => $office->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'role' => ExamRole::Proctor,
            'status' => AssignmentStatus::Confirmed,
        ]);
        $room = ExamRoom::factory()->create();
        $assignment->update(['exam_room_id' => $room->id]);
        $newSchool = \App\Models\School::factory()->forFieldOffice($office->id)->create();
        $newVenue = ExaminationSchool::factory()->create(['examination_id' => $exam->id, 'school_id' => $newSchool->id]);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/force-reassign", [
            'role' => 'room_examiner',
            'examination_school_id' => $newVenue->id,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(ExamRole::RoomExaminer, $assignment->role);
        $this->assertSame($newVenue->id, $assignment->examination_school_id);
        $this->assertNull($assignment->exam_room_id);
        $this->assertSame(AssignmentStatus::Confirmed, $assignment->status);
        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::AdminOverride)->count());
    }

    public function test_force_reassign_blocked_for_concluded_examination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $exam = Examination::factory()->create(['exam_date' => now()->subWeek()]);
        $assignment = ExamAssignment::factory()->create(['examination_id' => $exam->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/force-reassign", [
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
        ])->assertStatus(422);
    }

    public function test_fo_admin_cannot_force_reassign_another_offices_assignment(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $exam = Examination::factory()->create(['exam_date' => now()->addWeek()]);
        $assignment = ExamAssignment::factory()->create(['examination_id' => $exam->id, 'field_office_id' => $otherFo->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $exam->id]);

        $this->actingAs($foAdmin)->post("/assignments/{$assignment->id}/force-reassign", [
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
        ])->assertForbidden();
    }

    public function test_venue_must_belong_to_the_same_examination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $exam = Examination::factory()->create(['exam_date' => now()->addWeek()]);
        $assignment = ExamAssignment::factory()->create(['examination_id' => $exam->id]);
        $wrongVenue = ExaminationSchool::factory()->create();

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/force-reassign", [
            'role' => 'proctor',
            'examination_school_id' => $wrongVenue->id,
        ])->assertSessionHasErrors('examination_school_id');
    }

    public function test_super_admin_can_bulk_revoke_confirmed_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $a1 = ExamAssignment::factory()->create(['status' => AssignmentStatus::Confirmed]);
        $a2 = ExamAssignment::factory()->create(['status' => AssignmentStatus::Pending]);

        $this->actingAs($admin)->post('/assignments/bulk-revoke', [
            'assignment_ids' => [$a1->id, $a2->id],
        ])->assertRedirect();

        $this->assertModelMissing($a1);
        $this->assertModelMissing($a2);
        $this->assertTrue(AuditLog::where('action', 'designation_revoked')->exists());
    }

    public function test_only_super_admin_can_bulk_revoke(): void
    {
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $a1 = ExamAssignment::factory()->create();

        $this->actingAs($esd)->post('/assignments/bulk-revoke', [
            'assignment_ids' => [$a1->id],
        ])->assertForbidden();

        $this->assertModelExists($a1);
    }
}
