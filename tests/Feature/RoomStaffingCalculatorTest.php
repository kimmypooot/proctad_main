<?php

namespace Tests\Feature;

use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Services\RoomStaffingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomStaffingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function rooms(int $count): \Illuminate\Support\Collection
    {
        $venue = ExaminationSchool::factory()->create();
        $rooms = collect();
        for ($i = 1; $i <= $count; $i++) {
            $rooms->push(ExamRoom::factory()->create([
                'examination_school_id' => $venue->id,
                'room_number' => sprintf('Room-%03d', $i),
            ]));
        }

        return $rooms;
    }

    private function calculator(): RoomStaffingCalculator
    {
        return new RoomStaffingCalculator;
    }

    public function test_breakdown_propagates_supervising_examiner_to_the_whole_anchor_group(): void
    {
        $rooms = $this->rooms(6);
        $anchorRoom = $rooms->first();
        $assignments = collect([
            ['id' => 1, 'role' => 'supervising_examiner', 'exam_room_id' => $anchorRoom->id, 'status' => 'confirmed', 'member_name' => 'Juan Dela Cruz'],
        ]);

        $breakdown = $this->calculator()->breakdown($rooms, $assignments)->keyBy('room_number');

        // Rooms 1-5 (the anchor's group of 5) all credited with the same supervisor.
        foreach (['Room-001', 'Room-002', 'Room-003', 'Room-004', 'Room-005'] as $roomNumber) {
            $this->assertSame('Juan Dela Cruz', $breakdown[$roomNumber]['supervising_examiner']);
        }
        $this->assertTrue($breakdown['Room-001']['is_supervisor_anchor']);
        $this->assertFalse($breakdown['Room-002']['is_supervisor_anchor']);

        // Room 6 starts a new group with no supervisor assigned yet.
        $this->assertNull($breakdown['Room-006']['supervising_examiner']);
        $this->assertTrue($breakdown['Room-006']['is_supervisor_anchor']);
    }

    public function test_breakdown_excludes_declined_supervising_examiner(): void
    {
        $rooms = $this->rooms(5);
        $anchorRoom = $rooms->first();
        $assignments = collect([
            ['id' => 1, 'role' => 'supervising_examiner', 'exam_room_id' => $anchorRoom->id, 'status' => 'declined', 'member_name' => 'Juan Dela Cruz'],
        ]);

        $breakdown = $this->calculator()->breakdown($rooms, $assignments);

        $this->assertTrue($breakdown->every(fn ($room) => $room['supervising_examiner'] === null));
    }

    public function test_stats_excludes_declined_and_unplaced_assignments(): void
    {
        $rooms = $this->rooms(3);
        $placedRoom = $rooms->first();
        $assignments = collect([
            // Placed + confirmed — counts.
            ['id' => 1, 'role' => 'proctor', 'exam_room_id' => $placedRoom->id, 'status' => 'confirmed', 'member_name' => 'A'],
            // Placed but declined — must not count.
            ['id' => 2, 'role' => 'proctor', 'exam_room_id' => $rooms[1]->id, 'status' => 'declined', 'member_name' => 'B'],
            // Confirmed but not yet placed in a room — must not count.
            ['id' => 3, 'role' => 'room_examiner', 'exam_room_id' => null, 'status' => 'confirmed', 'member_name' => 'C'],
        ]);

        $stats = $this->calculator()->stats($rooms, $assignments);

        $this->assertSame(1, $stats['assigned']['proctor']);
        $this->assertSame(0, $stats['assigned']['room_examiner']);
        $this->assertSame(1, $stats['assigned_total']);
        $this->assertSame(3, $stats['required']['proctor']);
        $this->assertSame(1, $stats['required']['supervising_examiner']);
    }
}
