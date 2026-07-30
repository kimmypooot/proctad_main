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
use App\Notifications\AssignmentDeclined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->assertTrue($assignment->role->is(ExamRole::RoomExaminer));
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

    /* --- Recording a response on the member's behalf (no connectivity to answer the emailed link) --- */

    private function pendingAssignmentFor(FieldOffice $fo): ExamAssignment
    {
        $member = \App\Models\Member::factory()->create(['field_office_id' => $fo->id]);

        return ExamAssignment::factory()->create([
            'examination_id' => Examination::factory()->create(['exam_date' => now()->addWeek()])->id,
            'member_id' => $member->id,
            'field_office_id' => $fo->id,
            'testing_center_id' => $member->testing_center_id,
            'status' => AssignmentStatus::Pending,
        ]);
    }

    public function test_staff_can_record_a_confirmation_the_member_gave_by_phone(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'confirm',
            'channel' => 'phone',
            'note' => 'Called 09:15, spoke to the member directly.',
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Confirmed, $assignment->status);
        $this->assertNotNull($assignment->responded_at);

        // The row must say a human in the office answered for them — a status
        // flip alone would be indistinguishable from the member's own answer.
        $confirmation = $assignment->confirmations()->where('action', ConfirmationAction::Confirmed)->sole();
        $this->assertTrue($confirmation->metadata['on_behalf']);
        $this->assertSame($admin->id, $confirmation->metadata['recorded_by']);
        $this->assertSame('Phone call', $confirmation->metadata['channel_label']);
        $this->assertSame('Called 09:15, spoke to the member directly.', $confirmation->metadata['note']);
    }

    public function test_recording_a_decline_stores_the_reason_and_notifies_the_field_office(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'decline',
            'channel' => 'in_person',
            'decline_reason' => 'On medical leave that week.',
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Declined, $assignment->status);
        $this->assertSame('On medical leave that week.', $assignment->decline_reason);
        Notification::assertSentTo($admin, AssignmentDeclined::class);
    }

    public function test_recording_a_decline_requires_a_reason(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'decline',
            'channel' => 'phone',
        ])->assertSessionHasErrors('decline_reason');

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
    }

    /**
     * This records an answer the member could not deliver themselves; it is not
     * a licence to overwrite one they did deliver. Correcting a real response
     * stays a Force Reassign or a removal, both of which leave their own trail.
     */
    public function test_recording_cannot_overwrite_a_response_the_member_already_gave(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);
        $assignment->update(['status' => AssignmentStatus::Declined, 'decline_reason' => 'Family emergency.']);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'confirm',
            'channel' => 'phone',
        ])->assertRedirect()->assertSessionHas('error');

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Declined, $assignment->status);
        $this->assertSame('Family emergency.', $assignment->decline_reason);
    }

    public function test_fo_admin_cannot_record_a_response_for_another_centers_assignment(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => FieldOffice::factory()->create()->id]);
        $assignment = $this->pendingAssignmentFor(FieldOffice::factory()->create());

        $this->actingAs($foAdmin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'confirm',
            'channel' => 'phone',
        ])->assertForbidden();

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
    }

    public function test_response_cannot_be_recorded_for_a_concluded_examination(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);
        $assignment->examination->update(['exam_date' => now()->subWeek()]);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'confirm',
            'channel' => 'phone',
        ])->assertStatus(422);
    }

    /** Exam-day morning is exactly when a member finally gets through — the guard must not close the door then. */
    public function test_response_can_still_be_recorded_on_the_examination_day_itself(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);
        $assignment->examination->update(['exam_date' => today()]);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/record-response", [
            'action' => 'confirm',
            'channel' => 'phone',
        ])->assertRedirect();

        $this->assertSame(AssignmentStatus::Confirmed, $assignment->fresh()->status);
    }

    /** Bulk "Confirm Selected" is the same act at scale, so it must leave the same trail. */
    public function test_bulk_confirm_records_who_confirmed_on_the_members_behalf(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->pendingAssignmentFor($fo);

        $this->actingAs($admin)->post('/assignments/bulk-confirm', [
            'assignment_ids' => [$assignment->id],
        ])->assertRedirect();

        $this->assertSame(AssignmentStatus::Confirmed, $assignment->fresh()->status);
        $confirmation = $assignment->confirmations()->where('action', ConfirmationAction::Confirmed)->sole();
        $this->assertSame($admin->id, $confirmation->metadata['recorded_by']);
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
