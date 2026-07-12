<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use Illuminate\Support\Collection;

/**
 * Shared per-venue room-staffing math (required/assigned counts, per-room
 * breakdown) used by both the Examinations "Assign Rooms" step and the
 * standalone "Manage Rooms" page, so the two can't silently drift out of
 * sync with each other or with StaffingRandomizer's own anchor-group logic.
 */
class RoomStaffingCalculator
{
    // Matches StaffingRandomizer's default anchoring group size.
    public const ROOMS_PER_SUPERVISOR = 5;

    /**
     * @param  Collection  $assignments  rows: ['id','role','exam_room_id','status','member_name']
     */
    public function stats(Collection $rooms, Collection $assignments): array
    {
        $placed = $this->eligible($assignments)->whereNotNull('exam_room_id');

        $roomsCount = $rooms->count();
        $required = [
            'proctor' => $roomsCount,
            'room_examiner' => $roomsCount,
            'supervising_examiner' => (int) ceil($roomsCount / self::ROOMS_PER_SUPERVISOR),
        ];
        $requiredTotal = array_sum($required);
        $assignedByRole = $placed->countBy('role');
        $assignedTotal = $placed->count();

        return [
            'rooms_count' => $roomsCount,
            'total_capacity' => $rooms->sum('capacity'),
            'required' => $required,
            'assigned' => [
                'proctor' => $assignedByRole->get('proctor', 0),
                'room_examiner' => $assignedByRole->get('room_examiner', 0),
                'supervising_examiner' => $assignedByRole->get('supervising_examiner', 0),
            ],
            'required_total' => $requiredTotal,
            'assigned_total' => $assignedTotal,
            'ratio' => $requiredTotal > 0 ? min(100, round(($assignedTotal / $requiredTotal) * 100)) : null,
        ];
    }

    /**
     * Every room in a Supervising Examiner's group of ROOMS_PER_SUPERVISOR
     * rooms is credited with that anchor room's supervisor, since a single
     * SE assignment only ever links to the group's first (anchor) room.
     *
     * @param  Collection  $assignments  rows: ['id','role','exam_room_id','status','member_name']
     * @return Collection<int, array{id:int,room_number:string,capacity:int,designation:?string,proctor:?string,proctor_assignment_id:?int,room_examiner:?string,room_examiner_assignment_id:?int,supervising_examiner:?string,supervising_examiner_assignment_id:?int,is_supervisor_anchor:bool,complete:bool}>
     */
    public function breakdown(Collection $rooms, Collection $assignments): Collection
    {
        $eligible = $this->eligible($assignments);
        $sortedRooms = $rooms->sortBy(fn ($room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)->values();
        $supervisorByAnchorRoomId = $eligible->where('role', ExamRole::SupervisingExaminer->value)->keyBy('exam_room_id');

        return $sortedRooms->values()->map(function ($room, $index) use ($sortedRooms, $eligible, $supervisorByAnchorRoomId) {
            $groupStart = intdiv($index, self::ROOMS_PER_SUPERVISOR) * self::ROOMS_PER_SUPERVISOR;
            $anchorRoom = $sortedRooms->get($groupStart);
            $supervisor = $anchorRoom ? $supervisorByAnchorRoomId->get($anchorRoom->id) : null;
            $proctor = $eligible->first(fn ($a) => $a['role'] === ExamRole::Proctor->value && $a['exam_room_id'] === $room->id);
            $roomExaminer = $eligible->first(fn ($a) => $a['role'] === ExamRole::RoomExaminer->value && $a['exam_room_id'] === $room->id);

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'capacity' => $room->capacity,
                'designation' => $room->designation,
                'proctor' => $proctor['member_name'] ?? null,
                'proctor_assignment_id' => $proctor['id'] ?? null,
                'room_examiner' => $roomExaminer['member_name'] ?? null,
                'room_examiner_assignment_id' => $roomExaminer['id'] ?? null,
                'supervising_examiner' => $supervisor['member_name'] ?? null,
                'supervising_examiner_assignment_id' => $supervisor['id'] ?? null,
                'is_supervisor_anchor' => $index === $groupStart,
                'complete' => (bool) ($proctor && $roomExaminer && $supervisor),
            ];
        })->values();
    }

    /** Assignments a declined/cancelled/expired response shouldn't count toward staffing — matches StaffingRandomizer::pool(). */
    public function eligible(Collection $assignments): Collection
    {
        return $assignments->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value]);
    }
}
