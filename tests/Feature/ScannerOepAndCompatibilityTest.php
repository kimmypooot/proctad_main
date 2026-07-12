<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\OepAssignment;
use App\Models\OepAttendance;
use App\Models\OtherExaminationPersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScannerOepAndCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_oep_qr_payload_resolves_to_a_person(): void
    {
        $oep = OtherExaminationPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode("OEP:{$oep->oep_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('oepResult.oep_id', $oep->oep_id)
                ->where('result', null));
    }

    public function test_scanning_oep_at_a_venue_records_attendance_once(): void
    {
        $school = \App\Models\School::factory()->create(['name' => 'Leyte National High School']);
        $venue = ExaminationSchool::factory()->create(['school_id' => $school->id]);
        $oep = OtherExaminationPersonnel::factory()->create();
        OepAssignment::create(['other_examination_personnel_id' => $oep->id, 'examination_school_id' => $venue->id]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "OEP:{$oep->oep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.outcome', 'confirmed')
                ->where('attendance.venue', 'Leyte National High School')
                ->where('oepResult.venue', 'Leyte National High School'));

        $this->assertSame(1, OepAttendance::where('other_examination_personnel_id', $oep->id)
            ->where('examination_school_id', $venue->id)->count());

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "OEP:{$oep->oep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'already_confirmed'));
    }

    public function test_scanning_oep_without_venue_prompts_for_one(): void
    {
        $oep = OtherExaminationPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $exam = \App\Models\Examination::factory()->create();

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query(['code' => "OEP:{$oep->oep_id}", 'examination_id' => $exam->id]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'venue_required'));
    }

    public function test_oep_not_assigned_to_venue_is_reported(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $oep = OtherExaminationPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?'.http_build_query([
                'code' => "OEP:{$oep->oep_id}",
                'examination_id' => $venue->examination_id,
                'examination_school_id' => $venue->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'not_assigned'));
    }

    public function test_fo_admin_cannot_look_up_oep_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $oep = OtherExaminationPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)
            ->get('/scanner?code='.urlencode("OEP:{$oep->oep_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('oepResult', null)->where('notFound', true));
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

    public function test_lowercase_oep_prefix_is_recognized(): void
    {
        $oep = OtherExaminationPersonnel::factory()->create();
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->get('/scanner?code='.urlencode('oep:'.strtolower($oep->oep_id)))
            ->assertInertia(fn (Assert $page) => $page->where('oepResult.oep_id', $oep->oep_id));
    }

    public function test_attendance_summary_folds_in_oep_roster_when_venue_selected(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $present = OtherExaminationPersonnel::factory()->create();
        $absent = OtherExaminationPersonnel::factory()->create();
        OepAssignment::create(['other_examination_personnel_id' => $present->id, 'examination_school_id' => $venue->id]);
        $absentAssignment = OepAssignment::create(['other_examination_personnel_id' => $absent->id, 'examination_school_id' => $venue->id]);
        OepAttendance::create([
            'other_examination_personnel_id' => $present->id,
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
                ->where('attendanceSummary.roster.0.value', "oep:{$absentAssignment->id}")
                ->where('attendanceSummary.roster.0.code', $absent->oep_id));
    }

    public function test_bulk_mark_attendance_marks_oep_assignments_present(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $oep = OtherExaminationPersonnel::factory()->create();
        $assignment = OepAssignment::create(['other_examination_personnel_id' => $oep->id, 'examination_school_id' => $venue->id]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esd)
            ->post('/scanner/mark-attendance', [
                'type' => 'exam',
                'examination_id' => $venue->examination_id,
                'oep_assignment_ids' => [$assignment->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, OepAttendance::where('other_examination_personnel_id', $oep->id)
            ->where('examination_school_id', $venue->id)->count());
    }
}
