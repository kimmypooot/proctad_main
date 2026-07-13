<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAssignmentVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_be_assigned_to_a_venue_and_room(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $office->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);
        $member = Member::factory()->create(['field_office_id' => $office->id]);

        // A brand-new assignment only gets venue/role — the room is decided
        // later, once the member has confirmed their availability.
        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
        ])->assertRedirect();

        $assignment = ExamAssignment::firstOrFail();
        $this->assertSame($venue->id, $assignment->examination_school_id);
        $this->assertNull($assignment->exam_room_id);

        $assignment->update(['status' => 'confirmed']);

        $this->actingAs($admin)->patch("/assignments/{$assignment->id}/room", [
            'exam_room_id' => $room->id,
        ])->assertRedirect();

        $this->assertSame($room->id, $assignment->fresh()->exam_room_id);
    }

    public function test_room_cannot_be_assigned_before_the_member_confirms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $office->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $examination->id,
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch("/assignments/{$assignment->id}/room", [
            'exam_room_id' => $room->id,
        ])->assertSessionHasErrors('exam_room_id');

        $this->assertNull($assignment->fresh()->exam_room_id);
    }

    public function test_room_must_belong_to_the_selected_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $otherVenue = ExaminationSchool::factory()->create();
        $roomFromOtherVenue = ExamRoom::factory()->create(['examination_school_id' => $otherVenue->id]);
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
            'exam_room_id' => $roomFromOtherVenue->id,
        ])->assertSessionHasErrors('exam_room_id');
    }

    public function test_venue_must_belong_to_the_examination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $venueFromOtherExam = ExaminationSchool::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venueFromOtherExam->id,
        ])->assertSessionHasErrors('examination_school_id');
    }

    public function test_assignment_venue_and_room_can_be_updated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        // Confirmed: the room can only be set once the member has confirmed.
        $assignment = ExamAssignment::factory()->create(['member_id' => $member->id, 'role' => 'proctor', 'status' => 'confirmed']);
        $school = School::factory()->create(['field_office_id' => $office->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $assignment->examination_id, 'school_id' => $school->id]);
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'role' => 'proctor',
            'attended' => false,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room->id,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame($venue->id, $assignment->examination_school_id);
        $this->assertSame($room->id, $assignment->exam_room_id);
    }

    public function test_room_role_cannot_be_assigned_to_a_venue_outside_the_members_field_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $memberOffice = FieldOffice::factory()->create();
        $venueOffice = FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $venueOffice->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $member = Member::factory()->create(['field_office_id' => $memberOffice->id]);

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
        ])->assertSessionHasErrors('examination_school_id');

        $this->assertDatabaseCount('exam_assignments', 0);
    }

    public function test_non_room_role_can_cross_field_offices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $memberOffice = FieldOffice::factory()->create();
        $venueOffice = FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $venueOffice->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $member = Member::factory()->create(['field_office_id' => $memberOffice->id]);

        // REC/LEC-style committee roles are intentionally region-wide.
        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'rec_chair',
            'examination_school_id' => $venue->id,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('exam_assignments', 1);
    }

    public function test_bulk_store_rejects_a_room_role_venue_outside_any_members_field_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venueOffice = FieldOffice::factory()->create();
        $otherOffice = FieldOffice::factory()->create();
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $venueOffice->id]);
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id, 'school_id' => $school->id]);
        $inJurisdiction = Member::factory()->create(['field_office_id' => $venueOffice->id]);
        $outOfJurisdiction = Member::factory()->create(['field_office_id' => $otherOffice->id]);

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments/bulk", [
            'member_ids' => [$inJurisdiction->id, $outOfJurisdiction->id],
            'role' => 'room_examiner',
            'examination_school_id' => $venue->id,
        ])->assertSessionHasErrors('examination_school_id');

        $this->assertDatabaseCount('exam_assignments', 0);
    }

    public function test_update_rejects_moving_a_room_role_to_a_venue_outside_the_members_field_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $memberOffice = FieldOffice::factory()->create();
        $otherOffice = FieldOffice::factory()->create();
        $member = Member::factory()->create(['field_office_id' => $memberOffice->id]);
        $assignment = ExamAssignment::factory()->create(['member_id' => $member->id, 'role' => 'supervising_examiner']);
        $otherSchool = School::factory()->create(['field_office_id' => $otherOffice->id]);
        $otherVenue = ExaminationSchool::factory()->create(['examination_id' => $assignment->examination_id, 'school_id' => $otherSchool->id]);

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'role' => 'supervising_examiner',
            'attended' => false,
            'examination_school_id' => $otherVenue->id,
        ])->assertSessionHasErrors('examination_school_id');

        $this->assertNull($assignment->fresh()->examination_school_id);
    }

    public function test_force_reassign_rejects_a_venue_outside_the_members_field_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $memberOffice = FieldOffice::factory()->create();
        $otherOffice = FieldOffice::factory()->create();
        $member = Member::factory()->create(['field_office_id' => $memberOffice->id]);
        $exam = Examination::factory()->create(['exam_date' => now()->addWeek()]);
        $assignment = ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'role' => 'proctor',
        ]);
        $otherSchool = School::factory()->create(['field_office_id' => $otherOffice->id]);
        $otherVenue = ExaminationSchool::factory()->create(['examination_id' => $exam->id, 'school_id' => $otherSchool->id]);

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/force-reassign", [
            'role' => 'proctor',
            'examination_school_id' => $otherVenue->id,
        ])->assertSessionHasErrors('examination_school_id');

        $this->assertNull($assignment->fresh()->examination_school_id);
    }

    public function test_examination_show_page_lists_venues_and_rooms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        ExamRoom::factory()->create(['examination_school_id' => $venue->id]);

        $this->actingAs($admin)
            ->get("/examinations/{$examination->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Show')
                ->has('venues', 1)
                ->has('venues.0.rooms', 1));
    }
}
