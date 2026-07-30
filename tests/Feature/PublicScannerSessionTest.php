<?php

namespace Tests\Feature;

use App\Enums\TrainingSession;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\OtherExaminationPersonnel;
use App\Models\ScannerSession;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public /scan/{token} scanner: no login, but pinned to one event by a
 * revocable, expiring token issued by a staff member.
 */
class PublicScannerSessionTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $office;

    private User $issuer;

    private Member $member;

    private Examination $exam;

    private ExamAssignment $assignment;

    private ExaminationSchool $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->issuer = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->office->id,
        ]);
        $this->member = Member::factory()->create(['field_office_id' => $this->office->id]);
        $this->exam = Examination::factory()->create();
        $this->venue = ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->forFieldOffice($this->office->id)->create(),
        ]);
        $this->assignment = ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'member_id' => $this->member->id,
            'field_office_id' => $this->office->id,
            'role' => 'proctor',
        ]);
    }

    private function link(array $attributes = []): ScannerSession
    {
        return ScannerSession::create([
            'token' => ScannerSession::generateToken(),
            'label' => 'Main gate',
            'examination_id' => $this->exam->id,
            'examination_school_id' => $this->venue->id,
            'field_office_id' => $this->office->id,
            'created_by' => $this->issuer->id,
            'expires_at' => now()->addHours(8),
            ...$attributes,
        ]);
    }

    public function test_valid_token_confirms_attendance_without_logging_in(): void
    {
        $session = $this->link();

        $this->get("/scan/{$session->token}?code={$this->member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scanner/Index')
                ->where('result.proctad_id', $this->member->proctad_id)
                ->where('attendance.outcome', 'confirmed')
                ->where('publicSession.label', 'Main gate'));

        $this->assertNotNull($this->assignment->fresh()->attendance_confirmed_at);
        // Attribution follows the person who issued the link, not "nobody".
        $this->assertSame($this->issuer->id, $this->assignment->fresh()->attendance_confirmed_by);
    }

    public function test_public_payload_omits_service_history_and_employment_details(): void
    {
        $session = $this->link();

        $this->get("/scan/{$session->token}?code={$this->member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('result.service_history')
                ->missing('result.agency')
                ->missing('result.status')
                ->missing('result.status_label'));
    }

    public function test_authenticated_scanner_still_returns_the_full_payload(): void
    {
        $this->actingAs($this->issuer)
            ->get("/scanner?code={$this->member->proctad_id}&examination_id={$this->exam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('result.service_history')
                ->has('result.agency')
                ->where('publicSession', null));
    }

    public function test_token_context_wins_over_a_tampered_query_string(): void
    {
        $otherExam = Examination::factory()->create();
        $session = $this->link();

        $this->get("/scan/{$session->token}?code={$this->member->proctad_id}&examination_id={$otherExam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('examinationId', $this->exam->id));

        $this->assertNotNull($this->assignment->fresh()->attendance_confirmed_at);
    }

    public function test_expired_and_revoked_tokens_are_rejected(): void
    {
        $expired = $this->link(['expires_at' => now()->subMinute()]);
        $revoked = $this->link(['revoked_at' => now()]);

        $this->get("/scan/{$expired->token}")->assertForbidden();
        $this->get("/scan/{$revoked->token}")->assertForbidden();
        $this->get('/scan/not-a-real-token')->assertForbidden();
    }

    public function test_scanning_records_usage_and_an_audit_row(): void
    {
        $session = $this->link();

        $this->get("/scan/{$session->token}?code={$this->member->proctad_id}")->assertOk();

        $session->refresh();
        $this->assertSame(1, $session->scan_count);
        $this->assertNotNull($session->last_used_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'scanner_session_scan',
            'auditable_type' => ScannerSession::class,
            'auditable_id' => $session->id,
            'user_id' => $this->issuer->id,
        ]);
    }

    public function test_bulk_mark_attendance_through_a_link_ignores_a_posted_event_id(): void
    {
        $otherExam = Examination::factory()->create();
        $otherAssignment = ExamAssignment::factory()->create([
            'examination_id' => $otherExam->id,
            'member_id' => $this->member->id,
            'field_office_id' => $this->office->id,
            'role' => 'proctor',
        ]);
        $session = $this->link();

        $this->post("/scan/{$session->token}/mark-attendance", [
            'type' => 'exam',
            'examination_id' => $otherExam->id,
            'member_ids' => [$this->member->id],
        ])->assertRedirect();

        $this->assertNotNull($this->assignment->fresh()->attendance_confirmed_at);
        $this->assertNull($otherAssignment->fresh()->attendance_confirmed_at);
    }

    /**
     * The reach of a public link belongs to its issuer, so an FO-issued link
     * at a regional sitting legitimately meets members it cannot record. That
     * used to render as "No record found" — identical to a mistyped ID, and
     * read by venue staff as a broken QR rather than a scoping rule.
     */
    public function test_a_member_outside_the_links_reach_is_distinguished_from_a_bad_code(): void
    {
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $outsider = Member::factory()->create(['field_office_id' => $samar->id]);

        $training = Training::factory()->create([
            'training_date' => now()->toDateString(),
            'session' => TrainingSession::WholeDay,
            'field_office_id' => null,
        ]);

        $session = $this->link([
            'examination_id' => null,
            'examination_school_id' => null,
            'training_id' => $training->id,
        ]);

        $this->get("/scan/{$session->token}?code={$outsider->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('outOfReach', true)
                ->where('notFound', false)
                // Out of reach means out of reach: no name, no photo.
                ->where('result', null));

        $this->assertDatabaseMissing('training_assignments', [
            'training_id' => $training->id,
            'member_id' => $outsider->id,
        ]);

        // A code matching nobody still reads as not found.
        $this->get("/scan/{$session->token}?code=NOPE-0000")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notFound', true)
                ->where('outOfReach', false));
    }

    /**
     * Training attendance hangs off a TrainingAssignment, which only a member
     * can have. Scanning other examination personnel at a training recorded
     * nothing and said nothing — indistinguishable from a successful check-in.
     */
    public function test_personnel_scanned_at_a_training_are_told_nothing_was_recorded(): void
    {
        $oep = OtherExaminationPersonnel::factory()->create([
            'field_office_id' => $this->office->id,
        ]);

        $training = Training::factory()->create([
            'training_date' => now()->toDateString(),
            'session' => TrainingSession::WholeDay,
            'field_office_id' => $this->office->id,
        ]);

        $session = $this->link([
            'examination_id' => null,
            'examination_school_id' => null,
            'training_id' => $training->id,
        ]);

        $this->get("/scan/{$session->token}?code={$oep->oep_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Identity still resolves — the door still needs the face check.
                ->where('oepResult.oep_id', $oep->oep_id)
                ->where('attendance.outcome', 'members_only'));
    }

    public function test_staff_can_issue_and_revoke_a_link(): void
    {
        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'examination_id' => $this->exam->id,
                'examination_school_id' => $this->venue->id,
                'label' => 'Main gate',
                'expires_at' => now()->addHours(8)->toDateTimeString(),
            ])->assertRedirect();

        $session = ScannerSession::firstOrFail();
        $this->assertSame($this->issuer->id, $session->created_by);
        $this->assertTrue($session->isActive());

        $this->actingAs($this->issuer)
            ->post("/scanner-sessions/{$session->id}/revoke")
            ->assertRedirect();

        $this->assertFalse($session->fresh()->isActive());
        $this->get("/scan/{$session->token}")->assertForbidden();
    }

    public function test_members_cannot_issue_links(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
            'field_office_id' => $this->office->id,
        ]);

        $this->actingAs($member)
            ->post('/scanner-sessions', [
                'examination_id' => $this->exam->id,
                'expires_at' => now()->addHours(8)->toDateTimeString(),
            ])->assertForbidden();
    }

    public function test_fo_admin_cannot_revoke_another_field_offices_link(): void
    {
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $outsider = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $samar->id,
        ]);
        $session = $this->link();

        $this->actingAs($outsider)
            ->post("/scanner-sessions/{$session->id}/revoke")
            ->assertForbidden();
    }

    public function test_a_link_cannot_outlive_a_week(): void
    {
        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'examination_id' => $this->exam->id,
                'examination_school_id' => $this->venue->id,
                'expires_at' => now()->addMonth()->toDateTimeString(),
            ])->assertSessionHasErrors('expires_at');
    }

    /**
     * Trainings run as half-day AM and PM batches, and attendance is time-in
     * only: a scan creates the assignment. An AM link still live in the
     * afternoon would therefore write PM arrivals into the AM roster, so the
     * link is capped at the end of its own sitting rather than at a week.
     */
    public function test_a_training_link_cannot_outlive_its_sitting(): void
    {
        $training = Training::factory()->create([
            'training_date' => now()->addDay()->toDateString(),
            'session' => TrainingSession::Am,
            'field_office_id' => $this->office->id,
        ]);

        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'training_id' => $training->id,
                // Past noon — inside the week an examination link would allow.
                'expires_at' => now()->addDay()->setTime(15, 0)->toDateTimeString(),
            ])->assertSessionHasErrors('expires_at');

        $this->assertSame(0, ScannerSession::count());

        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'training_id' => $training->id,
                'expires_at' => now()->addDay()->setTime(11, 30)->toDateTimeString(),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, ScannerSession::count());
    }

    /**
     * A sitting already past falls back to the week-long cap, so attendance
     * can still be caught up after the fact — as it could before sittings
     * existed.
     */
    public function test_a_link_for_a_past_training_falls_back_to_the_week_cap(): void
    {
        $training = Training::factory()->create([
            'training_date' => now()->subWeek()->toDateString(),
            'session' => TrainingSession::Am,
            'field_office_id' => $this->office->id,
        ]);

        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'training_id' => $training->id,
                'expires_at' => now()->addHours(8)->toDateTimeString(),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, ScannerSession::count());
    }

    /**
     * OEP and covered-school attendance are both keyed to a venue, so a
     * venue-less examination link could only ever confirm directly-assigned
     * members — a silent half-scanner. Refuse to issue one.
     */
    public function test_an_examination_link_must_name_a_venue(): void
    {
        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'examination_id' => $this->exam->id,
                'expires_at' => now()->addHours(8)->toDateTimeString(),
            ])->assertSessionHasErrors('examination_school_id');

        $this->assertSame(0, ScannerSession::count());
    }

    public function test_a_link_cannot_name_another_examinations_venue(): void
    {
        $otherVenue = ExaminationSchool::factory()->create([
            'school_id' => School::factory()->forFieldOffice($this->office->id)->create(),
        ]);

        $this->actingAs($this->issuer)
            ->post('/scanner-sessions', [
                'examination_id' => $this->exam->id,
                'examination_school_id' => $otherVenue->id,
                'expires_at' => now()->addHours(8)->toDateTimeString(),
            ])->assertSessionHasErrors('examination_school_id');
    }

    /**
     * The link inherits the issuer's field-office scope: an FO Admin's link
     * must not become a region-wide lookup just because it is unauthenticated.
     */
    public function test_link_keeps_the_issuers_field_office_scope(): void
    {
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $outsideMember = Member::factory()->create(['field_office_id' => $samar->id]);
        $session = $this->link();

        $this->get("/scan/{$session->token}?code={$outsideMember->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Still withheld — the scan now names why rather than passing
                // the member off as nonexistent, but exposes no more than before.
                ->where('result', null)
                ->where('outOfReach', true));
    }
}
