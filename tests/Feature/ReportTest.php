<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/reports')
            ->assertForbidden();
    }

    public function test_staff_roles_can_view_reports(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::DirectorIv, UserRole::DirectorIii, UserRole::FieldDirector, UserRole::FoAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/reports')
                ->assertOk();
        }
    }

    public function test_field_office_scoped_roles_are_locked_to_their_own_field_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();

        $memberHere = Member::factory()->create(['field_office_id' => $fo->id, 'sex' => 'male']);
        $memberThere = Member::factory()->create(['field_office_id' => $otherFo->id, 'sex' => 'male']);
        ExamAssignment::factory()->create(['member_id' => $memberHere->id, 'field_office_id' => $fo->id]);
        ExamAssignment::factory()->create(['member_id' => $memberThere->id, 'field_office_id' => $otherFo->id]);

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        // Even if they try to pass a different field_office_id in the query, they stay locked to their own.
        $this->actingAs($foAdmin)
            ->get("/reports?field_office_id={$otherFo->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.field_office_id', $fo->id)
                ->where('summary.service_records', 1)
                ->where('filterOptions.canPickFieldOffice', false)
            );
    }

    public function test_region_wide_roles_can_filter_by_field_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();

        ExamAssignment::factory()->create(['field_office_id' => $fo->id]);
        ExamAssignment::factory()->create(['field_office_id' => $otherFo->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get("/reports?field_office_id={$fo->id}")
            ->assertInertia(fn (Assert $page) => $page->where('summary.service_records', 1));

        $this->actingAs($admin)
            ->get('/reports')
            ->assertInertia(fn (Assert $page) => $page->where('summary.service_records', 2));
    }

    public function test_reports_filter_by_year_exam_type_and_gender(): void
    {
        $examType = \App\Models\ExamType::create(['name' => 'CSE-PPT', 'is_active' => true]);
        $otherExamType = \App\Models\ExamType::create(['name' => 'FOE', 'is_active' => true]);

        $exam2026 = Examination::factory()->create(['exam_date' => '2026-08-09', 'exam_type_id' => $examType->id]);
        $exam2025 = Examination::factory()->create(['exam_date' => '2025-08-09', 'exam_type_id' => $otherExamType->id]);

        $male = Member::factory()->create(['sex' => 'male']);
        $female = Member::factory()->create(['sex' => 'female']);

        ExamAssignment::factory()->create(['examination_id' => $exam2026->id, 'member_id' => $male->id]);
        ExamAssignment::factory()->create(['examination_id' => $exam2025->id, 'member_id' => $female->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports?year=2026')
            ->assertInertia(fn (Assert $page) => $page->where('summary.service_records', 1));

        $this->actingAs($admin)->get("/reports?exam_type_id={$examType->id}")
            ->assertInertia(fn (Assert $page) => $page->where('summary.service_records', 1));

        $this->actingAs($admin)->get('/reports?sex=female')
            ->assertInertia(fn (Assert $page) => $page->where('summary.service_records', 1));
    }

    public function test_training_attendance_stats_reflect_confirmed_attendance(): void
    {
        $training = Training::factory()->create();
        TrainingAssignment::factory()->create(['training_id' => $training->id, 'attendance_confirmed_at' => now()]);
        TrainingAssignment::factory()->create(['training_id' => $training->id, 'attendance_confirmed_at' => null]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports')->assertInertia(fn (Assert $page) => $page
            ->where('trainingStats.total_participants', 2)
            ->where('trainingStats.attended', 1)
            ->where('trainingStats.attendance_rate', 50)
        );
    }

    public function test_venue_readiness_reflects_room_staffing(): void
    {
        $fo = FieldOffice::factory()->create();
        $center = \App\Models\TestingCenter::factory()->forFieldOffice($fo)->create(['name' => 'Tacloban City']);
        $school = \App\Models\School::create(['testing_center_id' => $center->id, 'name' => 'Test School', 'is_active' => true]);
        $exam = Examination::factory()->create();
        $venue = \App\Models\ExaminationSchool::create(['examination_id' => $exam->id, 'school_id' => $school->id, 'is_active' => true]);
        $room1 = \App\Models\ExamRoom::create(['examination_school_id' => $venue->id, 'room_number' => '1', 'capacity' => 30]);
        \App\Models\ExamRoom::create(['examination_school_id' => $venue->id, 'room_number' => '2', 'capacity' => 30]);

        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room1->id,
            'field_office_id' => $fo->id,
        ]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports')->assertInertia(fn (Assert $page) => $page
            ->where('venueReadiness.0.total_rooms', 2)
            ->where('venueReadiness.0.staffed_rooms', 1)
            ->where('venueReadiness.0.readiness_percent', 50)
        );
    }

    public function test_export_endpoints_return_excel_files(): void
    {
        Member::factory()->create();
        ExamAssignment::factory()->create();
        $training = Training::factory()->create();
        TrainingAssignment::factory()->create(['training_id' => $training->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports/export/members')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/reports/export/service-records')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/reports/export/training-attendance')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_member_service_history_can_be_printed_and_exported(): void
    {
        $member = Member::factory()->create();
        ExamAssignment::factory()->create(['member_id' => $member->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get("/members/{$member->id}/service-history/print")
            ->assertOk()
            ->assertSee($member->proctad_id);

        $this->actingAs($admin)->get("/members/{$member->id}/service-history/export")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_member_service_history_print_respects_field_office_scoping(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $member = Member::factory()->create(['field_office_id' => $otherFo->id]);

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->get("/members/{$member->id}/service-history/print")->assertForbidden();
    }
}
