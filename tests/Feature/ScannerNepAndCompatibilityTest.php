<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\NepAssignment;
use App\Models\NepAttendance;
use App\Models\NonExamPersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScannerNepAndCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nep_qr_payload_resolves_to_a_person(): void
    {
        $nep = NonExamPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode("NEP:{$nep->nep_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('nepResult.nep_id', $nep->nep_id)
                ->where('result', null));
    }

    public function test_scanning_nep_at_a_venue_records_attendance_once(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create();
        NepAssignment::create(['non_exam_personnel_id' => $nep->id, 'examination_school_id' => $venue->id]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "NEP:{$nep->nep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'confirmed'));

        $this->assertSame(1, NepAttendance::where('non_exam_personnel_id', $nep->id)
            ->where('examination_school_id', $venue->id)->count());

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "NEP:{$nep->nep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'already_confirmed'));
    }

    public function test_scanning_nep_without_venue_prompts_for_one(): void
    {
        $nep = NonExamPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $exam = \App\Models\Examination::factory()->create();

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query(['code' => "NEP:{$nep->nep_id}", 'examination_id' => $exam->id]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'venue_required'));
    }

    public function test_nep_not_assigned_to_venue_is_reported(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "NEP:{$nep->nep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'not_assigned'));
    }

    public function test_fo_admin_cannot_look_up_nep_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $nep = NonExamPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)
            ->get('/scanner?code='.urlencode("NEP:{$nep->nep_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('nepResult', null)->where('notFound', true));
    }

    public function test_legacy_pipe_suffixed_member_code_is_stripped_and_resolved(): void
    {
        $member = \App\Models\Member::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode("{$member->proctad_id}|attendance"))
            ->assertInertia(fn (Assert $page) => $page->where('result.proctad_id', $member->proctad_id));
    }

    public function test_legacy_bare_numeric_code_does_not_crash_and_reports_not_found(): void
    {
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode('7|attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('notFound', true));
    }

    public function test_lowercase_nep_prefix_is_recognized(): void
    {
        $nep = NonExamPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode('nep:'.strtolower($nep->nep_id)))
            ->assertInertia(fn (Assert $page) => $page->where('nepResult.nep_id', $nep->nep_id));
    }

    public function test_attendance_summary_folds_in_nep_roster_when_venue_selected(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $present = NonExamPersonnel::factory()->create();
        $absent = NonExamPersonnel::factory()->create();
        NepAssignment::create(['non_exam_personnel_id' => $present->id, 'examination_school_id' => $venue->id]);
        $absentAssignment = NepAssignment::create(['non_exam_personnel_id' => $absent->id, 'examination_school_id' => $venue->id]);
        NepAttendance::create([
            'non_exam_personnel_id' => $present->id,
            'examination_school_id' => $venue->id,
            'status' => 'present',
            'scan_method' => 'qr',
            'scanned_at' => now(),
        ]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendanceSummary.total', 2)
                ->where('attendanceSummary.present', 1)
                ->where('attendanceSummary.absent', 1)
                ->where('attendanceSummary.roster.0.value', "nep:{$absentAssignment->id}"));
    }

    public function test_bulk_mark_attendance_marks_nep_assignments_present(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create();
        $assignment = NepAssignment::create(['non_exam_personnel_id' => $nep->id, 'examination_school_id' => $venue->id]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->post('/scanner/mark-attendance', [
                'type' => 'exam',
                'examination_id' => $venue->examination_id,
                'nep_assignment_ids' => [$assignment->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, NepAttendance::where('non_exam_personnel_id', $nep->id)
            ->where('examination_school_id', $venue->id)->count());
    }
}
