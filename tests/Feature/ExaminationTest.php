<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role, ?FieldOffice $office = null): User
    {
        return User::factory()->create(['role' => $role, 'field_office_id' => $office?->id]);
    }

    public function test_only_esd_and_super_admin_manage_examinations(): void
    {
        $examType = ExamType::create(['name' => 'CSE-PPT Professional', 'is_active' => true]);
        $payload = ['title' => 'August 2026 CSE-PPT', 'exam_type_id' => $examType->id, 'exam_date' => '2026-08-09'];

        foreach ([UserRole::SuperAdmin, UserRole::EsdAdmin] as $role) {
            $this->actingAs($this->staff($role))
                ->post('/examinations', [...$payload, 'title' => "Exam by {$role->value}"])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);

        foreach ([UserRole::FoAdmin, UserRole::FieldDirector, UserRole::Management] as $role) {
            $user = $this->staff($role, $role->isFieldOfficeScoped() ? $office : null);
            $this->actingAs($user)->get('/examinations')->assertOk();
            $this->actingAs($user)->post('/examinations', $payload)->assertForbidden();
        }

        $this->actingAs($this->staff(UserRole::Member))->get('/examinations')->assertForbidden();
    }

    public function test_show_scopes_assignments_for_field_office_roles(): void
    {
        $leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $exam = Examination::factory()->create();

        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $leyte->id])->id,
            'field_office_id' => $leyte->id,
        ]);
        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $samar->id])->id,
            'field_office_id' => $samar->id,
        ]);

        $this->actingAs($this->staff(UserRole::FoAdmin, $leyte))
            ->get("/examinations/{$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->has('assignments', 1));

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->get("/examinations/{$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->has('assignments', 2));
    }

    public function test_index_exposes_stats_and_per_exam_staffing_counts(): void
    {
        $staffed = Examination::factory()->create(['exam_date' => now()->addDays(5)]);
        $unstaffed = Examination::factory()->create(['exam_date' => now()->subDays(5)]);

        $venue = ExaminationSchool::factory()->create(['examination_id' => $staffed->id]);
        ExamRoom::factory()->count(2)->create(['examination_school_id' => $venue->id]);

        ExamAssignment::factory()->create(['examination_id' => $staffed->id, 'status' => 'confirmed']);
        ExamAssignment::factory()->create(['examination_id' => $staffed->id, 'status' => 'confirmed']);
        ExamAssignment::factory()->create(['examination_id' => $unstaffed->id, 'status' => 'pending']);

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->get('/examinations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 2)
                ->where('stats.upcoming', 1)
                ->where('stats.fully_staffed', 1)
                ->where('examinations.0.id', $staffed->id)
                ->where('examinations.0.venues_count', 1)
                ->where('examinations.0.rooms_count', 2)
                ->where('examinations.0.confirmed_count', 2)
                ->where('examinations.0.staffing_ratio', 100)
                ->where('examinations.1.confirmed_count', 0)
                ->where('examinations.1.staffing_ratio', 0)
            );
    }

    public function test_show_exposes_committee_grouping_for_roles_and_assignments(): void
    {
        $fo = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $exam = Examination::factory()->create();

        ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => Member::factory()->create(['field_office_id' => $fo->id])->id,
            'field_office_id' => $fo->id,
            'role' => \App\Enums\ExamRole::RecChair,
        ]);

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->get("/examinations/{$exam->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignments.0.role_group', 'regional')
                ->where('assignments.0.role_group_label', 'Regional Examination Committee (REC)')
                ->where('roles.0.group', 'regional')
                ->where('roles.0.group_label', 'Regional Examination Committee (REC)')
            );
    }
}
