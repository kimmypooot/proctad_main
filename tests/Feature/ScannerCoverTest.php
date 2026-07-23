<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\ScannerSession;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Exam-day cover driven from the venue scanner, including the public
 * /scan/{token} link. Same rules as the admin console — both go through
 * AlternateActivator — plus the token pinning that keeps a leaked link from
 * reaching another venue's roster.
 */
class ScannerCoverTest extends TestCase
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
        $this->issuer = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->office->id,
        ]);
        $this->exam = Examination::factory()->create();
        $this->venue = $this->makeVenue();
    }

    private function makeVenue(): ExaminationSchool
    {
        return ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->create(['field_office_id' => $this->office->id]),
        ]);
    }

    private function link(?ExaminationSchool $venue = null): ScannerSession
    {
        return ScannerSession::create([
            'token' => ScannerSession::generateToken(),
            'label' => 'Main gate',
            'examination_id' => $this->exam->id,
            'examination_school_id' => ($venue ?? $this->venue)->id,
            'field_office_id' => $this->office->id,
            'created_by' => $this->issuer->id,
            'expires_at' => now()->addHours(8),
        ]);
    }

    private function seat(ExamRole $role, ?ExaminationSchool $venue = null): ExamAssignment
    {
        return ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'examination_school_id' => ($venue ?? $this->venue)->id,
            'member_id' => Member::factory()->create(['field_office_id' => $this->office->id])->id,
            'field_office_id' => $this->office->id,
            'role' => $role->value,
        ]);
    }

    public function test_a_public_link_can_mark_a_seat_absent(): void
    {
        $session = $this->link();
        $seat = $this->seat(ExamRole::Proctor);

        $this->post("/scan/{$session->token}/mark-absent", ['assignment_id' => $seat->id])
            ->assertRedirect();

        $this->assertNotNull($seat->fresh()->marked_absent_at);
        // Attributed to whoever issued the link, as scanning is.
        $this->assertSame($this->issuer->id, $seat->fresh()->marked_absent_by);
    }

    public function test_a_public_link_can_call_in_an_alternate(): void
    {
        $session = $this->link();
        $seat = $this->seat(ExamRole::Proctor);
        $alternate = $this->seat(ExamRole::AlternateExaminer);

        $this->post("/scan/{$session->token}/mark-absent", ['assignment_id' => $seat->id]);
        $this->post("/scan/{$session->token}/activate-alternate", [
            'assignment_id' => $seat->id,
            'alternate_assignment_id' => $alternate->id,
        ])->assertRedirect();

        $alternate->refresh();

        $this->assertSame(ExamRole::Proctor, $alternate->role);
        $this->assertSame($seat->id, $alternate->covering_for_assignment_id);
        $this->assertNotNull($alternate->attendance_confirmed_at);
    }

    /**
     * The pinning that matters: a leaked token is confined to its own venue,
     * so it cannot declare someone absent at a venue it was never issued for.
     */
    public function test_a_link_cannot_reach_another_venues_roster(): void
    {
        $session = $this->link();
        $elsewhere = $this->seat(ExamRole::Proctor, $this->makeVenue());

        $this->post("/scan/{$session->token}/mark-absent", ['assignment_id' => $elsewhere->id])
            ->assertNotFound();

        $this->assertNull($elsewhere->fresh()->marked_absent_at);
    }

    public function test_a_link_cannot_reach_another_examination(): void
    {
        $session = $this->link();
        $otherExam = Examination::factory()->create();
        $foreign = ExamAssignment::factory()->create([
            'examination_id' => $otherExam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $this->office->id])->id,
            'field_office_id' => $this->office->id,
            'role' => ExamRole::Proctor->value,
        ]);

        $this->post("/scan/{$session->token}/mark-absent", ['assignment_id' => $foreign->id])
            ->assertNotFound();
    }

    /** The scanner obeys the same refusals as the console. */
    public function test_the_scanner_enforces_the_same_rules(): void
    {
        $session = $this->link();
        $seat = $this->seat(ExamRole::Proctor);
        $alternate = $this->seat(ExamRole::AlternateExaminer);

        // Not yet declared vacant.
        $this->post("/scan/{$session->token}/activate-alternate", [
            'assignment_id' => $seat->id,
            'alternate_assignment_id' => $alternate->id,
        ])->assertSessionHas('error');

        $this->assertNull($alternate->fresh()->covering_for_assignment_id);
    }

    public function test_someone_already_scanned_in_cannot_be_marked_absent(): void
    {
        $session = $this->link();
        $seat = $this->seat(ExamRole::Proctor);
        $seat->update(['attendance_confirmed_at' => now()]);

        $this->post("/scan/{$session->token}/mark-absent", ['assignment_id' => $seat->id])
            ->assertSessionHas('error');

        $this->assertNull($seat->fresh()->marked_absent_at);
    }

    public function test_the_scanner_lists_seats_and_standby_alternates(): void
    {
        $session = $this->link();
        $seat = $this->seat(ExamRole::Proctor);
        $alternate = $this->seat(ExamRole::AlternateExaminer);

        $this->get("/scan/{$session->token}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($seat, $alternate) {
                $cover = $page->toArray()['props']['attendanceSummary']['cover'];

                $this->assertSame([$seat->id], collect($cover['seats'])->pluck('id')->all());
                $this->assertSame([$alternate->id], collect($cover['alternates'])->pluck('id')->all());
            });
    }

    /** An authenticated Testing Center Staff gets the same operations. */
    public function test_signed_in_staff_can_cover_from_the_scanner(): void
    {
        $seat = $this->seat(ExamRole::Proctor);

        $this->actingAs($this->issuer)
            ->post('/scanner/mark-absent', ['assignment_id' => $seat->id])
            ->assertRedirect();

        $this->assertNotNull($seat->fresh()->marked_absent_at);
    }

    public function test_the_stats_strip_reports_awaiting_not_absent(): void
    {
        $session = $this->link();
        $this->seat(ExamRole::Proctor);

        $this->get("/scan/{$session->token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Renamed: "awaiting" is not-yet-scanned. Real absence is the
                // deliberate judgement recorded in cover.seats[].absent.
                ->where('attendanceSummary.awaiting', 1)
                ->missing('attendanceSummary.absent'));
    }
}
