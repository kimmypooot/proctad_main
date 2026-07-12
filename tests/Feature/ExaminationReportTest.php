<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\FeeSchedule;
use App\Models\Member;
use App\Models\NepAssignment;
use App\Models\NonExamPersonnel;
use App\Models\School;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationReportTest extends TestCase
{
    use RefreshDatabase;

    private function setFeeRate(string $type, string $value, float $amount): void
    {
        FeeSchedule::updateOrCreate(
            ['payee_type' => $type, 'payee_value' => $value],
            ['amount_cents' => (int) round($amount * 100)],
        );
    }

    private function makeExaminationWithRoster(): array
    {
        $school = School::factory()->create();
        $exam = Examination::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $exam->id, 'school_id' => $school->id]);
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);

        $proctor = Member::factory()->create();
        $se = Member::factory()->create();

        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room->id,
            'member_id' => $proctor->id,
            'role' => ExamRole::Proctor,
            'status' => AssignmentStatus::Confirmed,
        ]);

        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room->id,
            'member_id' => $se->id,
            'role' => ExamRole::SupervisingExaminer,
            'status' => AssignmentStatus::Confirmed,
        ]);

        return [$exam, $venue, $room];
    }

    public function test_members_are_forbidden_from_generating_reports(): void
    {
        [$exam] = $this->makeExaminationWithRoster();

        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get("/examinations/{$exam->id}/reports/room-assignment")
            ->assertForbidden();
    }

    public function test_room_assignment_export_downloads_when_roster_is_confirmed(): void
    {
        [$exam] = $this->makeExaminationWithRoster();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/room-assignment")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_room_assignment_export_redirects_with_error_when_no_confirmed_proctors(): void
    {
        $exam = Examination::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/room-assignment")
            ->assertRedirect();

        $this->assertNotEmpty(session('error'));
    }

    public function test_payroll_export_blocks_when_fee_rate_missing(): void
    {
        [$exam] = $this->makeExaminationWithRoster();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll")
            ->assertRedirect();

        $this->assertStringContainsString('fee rate', session('error'));
    }

    public function test_payroll_export_downloads_when_rates_are_configured(): void
    {
        [$exam] = $this->makeExaminationWithRoster();
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::Proctor->value, 1400);
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::SupervisingExaminer->value, 1700);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_payroll_posting_export_blocks_when_no_signatory_configured(): void
    {
        [$exam, $venue] = $this->makeExaminationWithRoster();
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::Proctor->value, 1400);
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::SupervisingExaminer->value, 1700);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll-posting?venue_id={$venue->id}")
            ->assertRedirect();

        $this->assertStringContainsString('signatory', session('error'));
    }

    public function test_payroll_posting_requires_a_venue(): void
    {
        [$exam] = $this->makeExaminationWithRoster();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll-posting")
            ->assertRedirect();

        $this->assertStringContainsString('testing center', session('error'));
    }

    public function test_payroll_posting_expands_roster_beyond_ten_rows_without_error(): void
    {
        $school = School::factory()->create();
        $exam = Examination::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $exam->id, 'school_id' => $school->id]);

        Signatory::create(['name' => 'ATTY. TEST SIGNATORY', 'position' => 'REC Member', 'active' => true]);
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::Proctor->value, 1400);

        for ($i = 0; $i < 15; $i++) {
            $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);
            ExamAssignment::factory()->create([
                'examination_id' => $exam->id,
                'examination_school_id' => $venue->id,
                'exam_room_id' => $room->id,
                'role' => ExamRole::Proctor,
                'status' => AssignmentStatus::Confirmed,
            ]);
        }

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll-posting?venue_id={$venue->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_payroll_includes_non_exam_personnel(): void
    {
        [$exam, $venue] = $this->makeExaminationWithRoster();
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::Proctor->value, 1400);
        $this->setFeeRate(PayeeType::ExamRole->value, ExamRole::SupervisingExaminer->value, 1700);
        $this->setFeeRate(PayeeType::PersonnelType->value, PersonnelType::Paymaster->value, 1400);

        $nep = NonExamPersonnel::factory()->create(['personnel_type' => PersonnelType::Paymaster]);
        NepAssignment::create(['non_exam_personnel_id' => $nep->id, 'examination_school_id' => $venue->id, 'status' => 'assigned']);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/examinations/{$exam->id}/reports/payroll")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
