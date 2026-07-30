<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\ScannerSession;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Taking back a check-in made seconds ago.
 *
 * The guard rails matter more than the happy path here: the same endpoint is
 * reachable from a link shared around a venue, so anything it can erase, a
 * leaked link can erase.
 */
class ScannerUndoTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $office;

    private User $issuer;

    private Examination $exam;

    private ExaminationSchool $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->issuer = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $this->office->id]);
        $this->exam = Examination::factory()->create(['exam_date' => now()]);
        $this->venue = ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->forFieldOffice($this->office->id)->create(),
        ]);
    }

    private function link(?Examination $exam = null): ScannerSession
    {
        return ScannerSession::create([
            'token' => ScannerSession::generateToken(),
            'label' => 'Main gate',
            'examination_id' => ($exam ?? $this->exam)->id,
            'examination_school_id' => $exam ? null : $this->venue->id,
            'field_office_id' => $this->office->id,
            'created_by' => $this->issuer->id,
            'expires_at' => now()->addHours(8),
        ]);
    }

    private function checkedIn(?Examination $exam = null): ExamAssignment
    {
        $member = Member::factory()->create(['field_office_id' => $this->office->id]);

        return ExamAssignment::factory()->create([
            'examination_id' => ($exam ?? $this->exam)->id,
            'examination_school_id' => $exam ? null : $this->venue->id,
            'member_id' => $member->id,
            'field_office_id' => $this->office->id,
            'testing_center_id' => $member->testing_center_id,
            'role' => ExamRole::Proctor->value,
            'attendance_confirmed_at' => now(),
            'attendance_confirmed_by' => $this->issuer->id,
        ]);
    }

    public function test_a_public_link_can_undo_a_check_in_it_just_made(): void
    {
        $session = $this->link();
        $assignment = $this->checkedIn();

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect()->assertSessionHas('success');

        $assignment->refresh();
        $this->assertNull($assignment->attendance_confirmed_at);
        $this->assertNull($assignment->attendance_confirmed_by);
    }

    /** An erased check-in must stay visible to the office — otherwise a shared link can quietly un-attend someone. */
    public function test_undoing_leaves_an_audit_row_naming_the_link(): void
    {
        $session = $this->link();
        $assignment = $this->checkedIn();

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect();

        $log = AuditLog::where('action', 'attendance_undone')->sole();
        $this->assertSame(ExamAssignment::class, $log->auditable_type);
        $this->assertSame($assignment->id, $log->auditable_id);
        $this->assertSame($this->issuer->id, $log->user_id);
        $this->assertSame('Main gate', $log->changes['scanner_session']);
        $this->assertSame($session->token, $log->changes['scanner_token']);
    }

    /**
     * The window is the whole safety argument: enough for a mis-scan noticed at
     * the desk, not enough to un-attend somebody after the fact.
     */
    public function test_a_check_in_older_than_the_window_cannot_be_undone(): void
    {
        $session = $this->link();
        $assignment = $this->checkedIn();
        $assignment->update(['attendance_confirmed_at' => now()->subSeconds(30)]);

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull($assignment->fresh()->attendance_confirmed_at);
    }

    /** The same rule that stops a leaked link being re-pointed by editing the URL. */
    public function test_a_link_cannot_undo_a_check_in_from_another_examination(): void
    {
        $otherExam = Examination::factory()->create(['exam_date' => now()]);
        $session = $this->link();
        $assignment = $this->checkedIn($otherExam);

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull($assignment->fresh()->attendance_confirmed_at);
    }

    /** Check-in queues a Certificate of Appreciation; undoing it must take that back too. */
    public function test_undoing_withdraws_the_certificate_the_check_in_queued(): void
    {
        $session = $this->link();
        $assignment = $this->checkedIn();
        $certificate = Certificate::create([
            'type' => CertificateType::Appreciation,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'status' => CertificateStatus::Pending,
            'requested_by' => $this->issuer->id,
        ]);

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect();

        $this->assertModelMissing($certificate);
    }

    /**
     * A released certificate has a number and has already been emailed —
     * deleting the row would not unsend it, so it stays and the operator is
     * told rather than being left to assume the undo was clean.
     */
    public function test_undoing_leaves_an_already_released_certificate_in_place(): void
    {
        $session = $this->link();
        $assignment = $this->checkedIn();
        $certificate = Certificate::create([
            'type' => CertificateType::Appreciation,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'status' => CertificateStatus::Released,
            'requested_by' => $this->issuer->id,
        ]);

        $this->post("/scan/{$session->token}/undo-attendance", [
            'kind' => 'exam_assignment',
            'id' => $assignment->id,
        ])->assertRedirect();

        $this->assertModelExists($certificate);
        $this->assertNull($assignment->fresh()->attendance_confirmed_at);

        $log = AuditLog::where('action', 'attendance_undone')->sole();
        $this->assertSame(1, $log->changes['certificates_kept']);
    }

    public function test_signed_in_staff_can_undo_through_the_staff_scanner(): void
    {
        $assignment = $this->checkedIn();

        $this->actingAs($this->issuer)
            ->post('/scanner/undo-attendance', ['kind' => 'exam_assignment', 'id' => $assignment->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull($assignment->fresh()->attendance_confirmed_at);
    }

    public function test_staff_cannot_undo_a_check_in_outside_their_testing_center(): void
    {
        $assignment = $this->checkedIn();
        $stranger = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM'])->id,
        ]);

        $this->actingAs($stranger)
            ->post('/scanner/undo-attendance', ['kind' => 'exam_assignment', 'id' => $assignment->id])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull($assignment->fresh()->attendance_confirmed_at);
    }
}
