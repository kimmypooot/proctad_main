<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffingRandomizerTest extends TestCase
{
    use RefreshDatabase;

    private function venueWithRooms(int $roomCount = 3): ExaminationSchool
    {
        $venue = ExaminationSchool::factory()->create();
        for ($i = 1; $i <= $roomCount; $i++) {
            ExamRoom::factory()->create([
                'examination_school_id' => $venue->id,
                'room_number' => sprintf('Room-%03d', $i),
            ]);
        }

        return $venue;
    }

    private function roomAssignment(ExaminationSchool $venue, ExamRole $role): ExamAssignment
    {
        return ExamAssignment::factory()->create([
            'examination_id' => $venue->examination_id,
            'examination_school_id' => $venue->id,
            'role' => $role,
            'status' => AssignmentStatus::Confirmed,
        ]);
    }

    public function test_randomize_fills_every_room_when_pool_is_sufficient(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueWithRooms(3);
        $proctors = collect(range(1, 3))->map(fn () => $this->roomAssignment($venue, ExamRole::Proctor));

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'all'])
            ->assertRedirect();

        $roomIds = $proctors->map(fn ($a) => $a->fresh()->exam_room_id)->filter();
        $this->assertCount(3, $roomIds->unique());
    }

    public function test_randomize_all_clears_existing_room_links_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueWithRooms(2);
        $room = ExamRoom::where('examination_school_id', $venue->id)->first();
        $stale = $this->roomAssignment($venue, ExamRole::Proctor);
        $stale->update(['exam_room_id' => $room->id]);

        // No other proctors in the pool this time — after clearing, nothing refills it.
        $stale->update(['status' => AssignmentStatus::Declined]);

        $this->actingAs($admin)->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'all']);

        $this->assertNull($stale->fresh()->exam_room_id);
    }

    public function test_unfilled_scope_does_not_touch_already_assigned_rooms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueWithRooms(2);
        $rooms = ExamRoom::where('examination_school_id', $venue->id)->orderBy('room_number')->get();

        $filled = $this->roomAssignment($venue, ExamRole::Proctor);
        $filled->update(['exam_room_id' => $rooms[0]->id]);
        $unfilled = $this->roomAssignment($venue, ExamRole::Proctor);

        $this->actingAs($admin)->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'unfilled']);

        $this->assertSame($rooms[0]->id, $filled->fresh()->exam_room_id);
        $this->assertSame($rooms[1]->id, $unfilled->fresh()->exam_room_id);
    }

    public function test_supervising_examiners_are_anchored_to_room_groups(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueWithRooms(12);
        $rooms = ExamRoom::where('examination_school_id', $venue->id)->orderBy('room_number')->get();
        $se1 = $this->roomAssignment($venue, ExamRole::SupervisingExaminer);
        $se2 = $this->roomAssignment($venue, ExamRole::SupervisingExaminer);

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'all', 'rooms_per_supervisor' => 5]);

        $anchors = collect([$se1->fresh()->exam_room_id, $se2->fresh()->exam_room_id]);
        $this->assertTrue($anchors->contains($rooms[0]->id));
        $this->assertTrue($anchors->contains($rooms[5]->id));
    }

    public function test_clear_removes_all_room_links_for_the_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = $this->venueWithRooms(1);
        $room = ExamRoom::where('examination_school_id', $venue->id)->first();
        $assignment = $this->roomAssignment($venue, ExamRole::Proctor);
        $assignment->update(['exam_room_id' => $room->id]);

        $this->actingAs($admin)->post("/venues/{$venue->id}/staffing/clear")->assertRedirect();

        $this->assertNull($assignment->fresh()->exam_room_id);
    }

    public function test_fo_admin_cannot_randomize_another_offices_venue(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->create(['field_office_id' => $otherFo->id]);
        $venue = ExaminationSchool::factory()->create(['school_id' => $school->id]);

        $this->actingAs($foAdmin)
            ->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'all'])
            ->assertForbidden();
    }

    public function test_randomize_without_rooms_reports_a_clear_error(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/staffing/randomize", ['scope' => 'all'])
            ->assertSessionHas('error');
    }
}
