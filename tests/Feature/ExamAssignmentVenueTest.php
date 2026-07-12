<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAssignmentVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_be_assigned_to_a_venue_and_room(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);
        $member = Member::factory()->create();

        $this->actingAs($admin)->post("/examinations/{$examination->id}/assignments", [
            'member_id' => $member->id,
            'role' => 'proctor',
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room->id,
        ])->assertRedirect();

        $assignment = ExamAssignment::firstOrFail();
        $this->assertSame($venue->id, $assignment->examination_school_id);
        $this->assertSame($room->id, $assignment->exam_room_id);
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
        $assignment = ExamAssignment::factory()->create();
        $venue = ExaminationSchool::factory()->create(['examination_id' => $assignment->examination_id]);
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
