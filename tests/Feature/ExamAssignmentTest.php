<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Jobs\SendAssignmentConfirmation;
use App\Models\AuditLog;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    /** Room-role assignments must stay inside the venue's field office, which
     *  the venue inherits from its school — so the school drives the match. */
    private function venueIn(FieldOffice $office): ExaminationSchool
    {
        return ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->create(['field_office_id' => $office->id])->id,
        ]);
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

    /**
     * Batch deployment queues its confirmations rather than sending one SMTP
     * round-trip per member inside the request. Only members who got a venue
     * need confirming, so a bulk assign without one queues nothing.
     */
    public function test_bulk_assign_queues_a_confirmation_per_member_given_a_venue(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueIn($this->leyte);
        $a = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $b = Member::factory()->create(['field_office_id' => $this->leyte->id]);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments/bulk", [
                'member_ids' => [$a->id, $b->id],
                'role' => ExamRole::Proctor->value,
                'examination_school_id' => $venue->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Queue::assertPushed(SendAssignmentConfirmation::class, 2);
    }

    public function test_bulk_assign_without_a_venue_queues_nothing(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments/bulk", [
                'member_ids' => [Member::factory()->create()->id],
                'role' => ExamRole::Proctor->value,
            ])
            ->assertRedirect();

        Queue::assertNothingPushed();
    }

    /**
     * Single sends stay synchronous so the caller can still report a bounce
     * inline — see AssignmentConfirmationController's `status === 'failed'`
     * branch, which a queued send could never reach.
     */
    public function test_single_assignment_sends_its_confirmation_synchronously(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueIn($this->leyte);

        $this->actingAs($admin)
            ->post("/examinations/{$this->exam->id}/assignments", [
                'member_id' => Member::factory()->create(['field_office_id' => $this->leyte->id])->id,
                'role' => ExamRole::Proctor->value,
                'examination_school_id' => $venue->id,
            ])
            ->assertRedirect();

        Queue::assertNotPushed(SendAssignmentConfirmation::class);
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
            // Room assignment requires the member to have already confirmed.
            'status' => AssignmentStatus::Confirmed->value,
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
