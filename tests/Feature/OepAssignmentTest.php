<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\OepAssignment;
use App\Models\OepAttendance;
use App\Models\OtherExaminationPersonnel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OepAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_personnel_to_a_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $oep = OtherExaminationPersonnel::factory()->create();

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/oep-assignments", ['other_examination_personnel_id' => $oep->id])
            ->assertRedirect();

        $this->assertSame(1, OepAssignment::where('examination_school_id', $venue->id)
            ->where('other_examination_personnel_id', $oep->id)->count());
    }

    public function test_same_person_cannot_be_assigned_twice_to_the_same_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $oep = OtherExaminationPersonnel::factory()->create();
        OepAssignment::create(['other_examination_personnel_id' => $oep->id, 'examination_school_id' => $venue->id]);

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/oep-assignments", ['other_examination_personnel_id' => $oep->id])
            ->assertSessionHasErrors('other_examination_personnel_id');
    }

    public function test_fo_admin_cannot_assign_personnel_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->create(['field_office_id' => $fo->id]);
        $venue = ExaminationSchool::factory()->create(['school_id' => $school->id]);
        $oep = OtherExaminationPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)
            ->post("/venues/{$venue->id}/oep-assignments", ['other_examination_personnel_id' => $oep->id])
            ->assertForbidden();
    }

    public function test_admin_can_remove_a_oep_assignment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = OepAssignment::factory()->create();

        $this->actingAs($admin)->delete("/oep-assignments/{$assignment->id}")->assertRedirect();
        $this->assertModelMissing($assignment);
    }

    public function test_attendance_can_be_marked_and_unmarked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = OepAssignment::factory()->create();

        $this->actingAs($admin)
            ->patch("/oep-assignments/{$assignment->id}/attendance", ['present' => true])
            ->assertRedirect();

        $this->assertSame(1, OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());

        $this->actingAs($admin)
            ->patch("/oep-assignments/{$assignment->id}/attendance", ['present' => false])
            ->assertRedirect();

        $this->assertSame(0, OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());
    }

    public function test_marking_attendance_twice_does_not_duplicate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = OepAssignment::factory()->create();

        $this->actingAs($admin)->patch("/oep-assignments/{$assignment->id}/attendance", ['present' => true]);
        $this->actingAs($admin)->patch("/oep-assignments/{$assignment->id}/attendance", ['present' => true]);

        $this->assertSame(1, OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)->count());
    }

    public function test_examination_show_page_lists_oep_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $oep = OtherExaminationPersonnel::factory()->create(['personnel_type' => \App\Enums\PersonnelType::Driver]);
        $assignment = OepAssignment::factory()->create([
            'examination_school_id' => $venue->id,
            'other_examination_personnel_id' => $oep->id,
        ]);
        OepAttendance::create([
            'other_examination_personnel_id' => $assignment->other_examination_personnel_id,
            'examination_school_id' => $venue->id,
            'status' => 'present',
            'scan_method' => 'manual',
            'scanned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/examinations/{$venue->examination_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('venues.0.oep_assignments', 1)
                ->where('venues.0.oep_assignments.0.present', true)
                ->where('venues.0.oep_assignments.0.personnel_type_label', 'Driver')
                ->where('venues.0.oep_assignments.0.role_group_label', 'Support Personnel'));
    }
}
