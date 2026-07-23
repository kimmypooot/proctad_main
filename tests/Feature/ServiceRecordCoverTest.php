<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Exports\ServiceRecordsExport;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use App\Services\AlternateActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Exam-day cover as it reaches the record: a no-show must not read as a
 * clerical gap, and a called-in reserve's day must read as service rendered.
 */
class ServiceRecordCoverTest extends TestCase
{
    use RefreshDatabase;

    private Examination $exam;

    private ExaminationSchool $venue;

    private FieldOffice $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->exam = Examination::factory()->create(['exam_date' => '2026-08-09']);
        $this->venue = ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->create(['field_office_id' => $this->office->id]),
        ]);
    }

    private function seat(ExamRole $role, ?Member $member = null): ExamAssignment
    {
        return ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'examination_school_id' => $this->venue->id,
            'member_id' => ($member ?? Member::factory()->create(['field_office_id' => $this->office->id]))->id,
            'field_office_id' => $this->office->id,
            'role' => $role->value,
        ]);
    }

    /** @return array{0: ExamAssignment, 1: ExamAssignment} the no-show and the substitute */
    private function coveredSeat(?Member $alternateMember = null): array
    {
        $activator = app(AlternateActivator::class);
        $vacant = $this->seat(ExamRole::Proctor);
        $alternate = $this->seat(ExamRole::AlternateExaminer, $alternateMember);

        $activator->markAbsent($vacant, User::factory()->create(['role' => UserRole::FoAdmin]));
        $activator->activate($alternate, $vacant);

        return [$vacant->fresh(), $alternate->fresh()];
    }

    public function test_a_no_show_is_recorded_as_absent_not_merely_unconfirmed(): void
    {
        [$vacant] = $this->coveredSeat();

        $this->assertSame('Absent', $vacant->attendanceOutcome());
        $this->assertStringContainsString('Did not report', $vacant->serviceNote());
    }

    public function test_a_substitutes_record_names_who_they_covered(): void
    {
        [$vacant, $alternate] = $this->coveredSeat();

        $this->assertSame('Present', $alternate->attendanceOutcome());
        $this->assertStringContainsString('Alternate Examiner', $alternate->serviceNote());
        $this->assertStringContainsString($vacant->member->name, $alternate->serviceNote());
    }

    public function test_an_ordinary_assignment_carries_no_note(): void
    {
        $seat = $this->seat(ExamRole::Proctor);

        $this->assertNull($seat->serviceNote());
        $this->assertSame('Not recorded', $seat->attendanceOutcome());
    }

    public function test_the_member_sees_the_cover_note_on_their_own_history(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->office->id]);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member->update(['user_id' => $user->id]);

        [$vacant] = $this->coveredSeat($member);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertInertia(function (Assert $page) use ($vacant) {
                $record = collect($page->toArray()['props']['records'])->firstWhere('role_label', 'Proctor');

                $this->assertSame('Present', $record['attendance_outcome']);
                $this->assertStringContainsString($vacant->member->name, $record['service_note']);
            });
    }

    /**
     * The export previously answered "Attendance Confirmed: No" for both a
     * recorded absence and a missing scan — the same cell for two very
     * different facts.
     */
    public function test_the_export_distinguishes_absence_from_an_unrecorded_scan(): void
    {
        [$vacant, $alternate] = $this->coveredSeat();
        $untouched = $this->seat(ExamRole::RoomExaminer);

        $export = new ServiceRecordsExport;
        $rows = $export->collection()->map(fn ($a) => $export->map($a))->keyBy(fn ($row) => $row[0]);

        $headings = $export->headings();
        $attendance = array_search('Attendance', $headings, true);
        $note = array_search('Service Note', $headings, true);

        $this->assertSame('Absent', $rows[$vacant->member->proctad_id][$attendance]);
        $this->assertSame('Present', $rows[$alternate->member->proctad_id][$attendance]);
        $this->assertSame('Not recorded', $rows[$untouched->member->proctad_id][$attendance]);

        $this->assertStringContainsString('covering for', $rows[$alternate->member->proctad_id][$note]);
        $this->assertNull($rows[$untouched->member->proctad_id][$note]);
    }

    public function test_the_printed_record_states_the_outcome(): void
    {
        [$vacant] = $this->coveredSeat();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get("/members/{$vacant->member_id}/service-history/print")
            ->assertOk()
            ->assertSee('Absent')
            ->assertSee('Did not report on exam day', false);
    }
}
