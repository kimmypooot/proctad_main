<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\NepAssignment;
use App\Models\NepAttendance;
use App\Models\NonExamPersonnel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NepAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_personnel_to_a_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create();

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/nep-assignments", ['non_exam_personnel_id' => $nep->id])
            ->assertRedirect();

        $this->assertSame(1, NepAssignment::where('examination_school_id', $venue->id)
            ->where('non_exam_personnel_id', $nep->id)->count());
    }

    public function test_same_person_cannot_be_assigned_twice_to_the_same_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create();
        NepAssignment::create(['non_exam_personnel_id' => $nep->id, 'examination_school_id' => $venue->id]);

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/nep-assignments", ['non_exam_personnel_id' => $nep->id])
            ->assertSessionHasErrors('non_exam_personnel_id');
    }

    public function test_fo_admin_cannot_assign_personnel_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->create(['field_office_id' => $fo->id]);
        $venue = ExaminationSchool::factory()->create(['school_id' => $school->id]);
        $nep = NonExamPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)
            ->post("/venues/{$venue->id}/nep-assignments", ['non_exam_personnel_id' => $nep->id])
            ->assertForbidden();
    }

    public function test_admin_can_remove_a_nep_assignment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = NepAssignment::factory()->create();

        $this->actingAs($admin)->delete("/nep-assignments/{$assignment->id}")->assertRedirect();
        $this->assertModelMissing($assignment);
    }

    public function test_attendance_can_be_marked_and_unmarked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = NepAssignment::factory()->create();

        $this->actingAs($admin)
            ->patch("/nep-assignments/{$assignment->id}/attendance", ['present' => true])
            ->assertRedirect();

        $this->assertSame(1, NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());

        $this->actingAs($admin)
            ->patch("/nep-assignments/{$assignment->id}/attendance", ['present' => false])
            ->assertRedirect();

        $this->assertSame(0, NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());
    }

    public function test_marking_attendance_twice_does_not_duplicate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = NepAssignment::factory()->create();

        $this->actingAs($admin)->patch("/nep-assignments/{$assignment->id}/attendance", ['present' => true]);
        $this->actingAs($admin)->patch("/nep-assignments/{$assignment->id}/attendance", ['present' => true]);

        $this->assertSame(1, NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());
    }

    public function test_examination_show_page_lists_nep_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $nep = NonExamPersonnel::factory()->create(['personnel_type' => \App\Enums\PersonnelType::Driver]);
        $assignment = NepAssignment::factory()->create([
            'examination_school_id' => $venue->id,
            'non_exam_personnel_id' => $nep->id,
        ]);
        NepAttendance::create([
            'non_exam_personnel_id' => $assignment->non_exam_personnel_id,
            'examination_school_id' => $venue->id,
            'status' => 'present',
            'scan_method' => 'manual',
            'scanned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/examinations/{$venue->examination_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('venues.0.nep_assignments', 1)
                ->where('venues.0.nep_assignments.0.present', true)
                ->where('venues.0.nep_assignments.0.personnel_type_label', 'Driver')
                ->where('venues.0.nep_assignments.0.role_group_label', 'Support Personnel'));
    }
}
