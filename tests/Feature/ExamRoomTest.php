<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamRoomTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::EsdAdmin]);
    }

    public function test_index_shows_rooms_and_staffing_stats(): void
    {
        $venue = ExaminationSchool::factory()->create();
        ExamRoom::factory()->count(3)->create(['examination_school_id' => $venue->id]);

        $this->actingAs($this->admin())
            ->get("/venues/{$venue->id}/rooms")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Rooms')
                ->where('stats.rooms_count', 3)
                ->has('rooms', 3));
    }

    public function test_room_staffing_map_propagates_supervising_examiner_to_whole_group_and_excludes_declined(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $rooms = collect();
        for ($i = 1; $i <= 6; $i++) {
            $rooms->push(\App\Models\ExamRoom::factory()->create([
                'examination_school_id' => $venue->id,
                'room_number' => sprintf('Room-%03d', $i),
            ]));
        }
        $anchorRoom = $rooms->first();
        $supervisor = \App\Models\Member::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'suffix' => null, 'last_name' => 'Dela Cruz']);

        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $venue->examination_id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $anchorRoom->id,
            'member_id' => $supervisor->id,
            'role' => 'supervising_examiner',
            'status' => 'confirmed',
        ]);
        // Declined Proctor in room 2 — must not count as staffed.
        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $venue->examination_id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $rooms[1]->id,
            'role' => 'proctor',
            'status' => 'declined',
        ]);

        $this->actingAs($this->admin())
            ->get("/venues/{$venue->id}/rooms")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('roomBreakdown.0.is_supervisor_anchor', true)
                ->where('roomBreakdown.0.supervising_examiner', 'DELA CRUZ, JUAN')
                ->where('roomBreakdown.1.is_supervisor_anchor', false)
                ->where('roomBreakdown.1.supervising_examiner', 'DELA CRUZ, JUAN')
                ->where('stats.assigned.proctor', 0)
                ->where('stats.assigned.supervising_examiner', 1)
                ->where('stats.assigned_total', 1));
    }

    public function test_bulk_generate_replaces_all_existing_rooms(): void
    {
        $venue = ExaminationSchool::factory()->create();
        ExamRoom::factory()->count(2)->create(['examination_school_id' => $venue->id]);

        $this->actingAs($this->admin())
            ->post("/venues/{$venue->id}/rooms/generate", ['count' => 5, 'capacity' => 30])
            ->assertRedirect();

        $this->assertSame(5, $venue->rooms()->count());
        $this->assertSame('Room-001', $venue->rooms()->orderBy('room_number')->first()->room_number);
    }

    public function test_bulk_add_appends_rooms_without_deleting_existing(): void
    {
        $venue = ExaminationSchool::factory()->create();
        ExamRoom::factory()->create(['examination_school_id' => $venue->id, 'room_number' => 'Room-001']);

        $this->actingAs($this->admin())
            ->post("/venues/{$venue->id}/rooms/add-more", ['count' => 2, 'capacity' => 25])
            ->assertRedirect();

        $this->assertSame(3, $venue->rooms()->count());
    }

    public function test_clear_all_removes_every_room(): void
    {
        $venue = ExaminationSchool::factory()->create();
        ExamRoom::factory()->count(4)->create(['examination_school_id' => $venue->id]);

        $this->actingAs($this->admin())
            ->delete("/venues/{$venue->id}/rooms")
            ->assertRedirect();

        $this->assertSame(0, $venue->rooms()->count());
    }

    public function test_override_designation_applies_to_undesignated_rooms_only(): void
    {
        $venue = ExaminationSchool::factory()->create();
        $designated = ExamRoom::factory()->create(['examination_school_id' => $venue->id, 'designation' => 'BCLTE']);
        $undesignated = ExamRoom::factory()->create(['examination_school_id' => $venue->id, 'designation' => null]);

        $this->actingAs($this->admin())
            ->post("/venues/{$venue->id}/rooms/designations", ['designation' => 'Professional', 'scope' => 'undesignated'])
            ->assertRedirect();

        $this->assertSame('BCLTE', $designated->fresh()->designation);
        $this->assertSame('Professional', $undesignated->fresh()->designation);
    }
}
