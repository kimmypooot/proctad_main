<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\ExaminationSchool;
use Illuminate\Support\Collection;

/**
 * Auto-assigns already-confirmed-or-pending School-role staff (Proctor, Room
 * Examiner, Supervising Examiner) to rooms within a venue. Ported from the
 * legacy randomize-staffing.php: each role's pool is shuffled independently,
 * then assigned to rooms in ascending room-number order. Supervising
 * Examiners are anchored one per group of $roomsPerSupervisor consecutive
 * rooms (SE #1 -> room 1, SE #2 -> room 6, ... for the default group size 5).
 */
class StaffingRandomizer
{
    private const ROOM_ROLES = [ExamRole::Proctor, ExamRole::RoomExaminer, ExamRole::SupervisingExaminer];

    /** @return array{proctors: int, examiners: int, supervisors: int} */
    public function randomize(ExaminationSchool $venue, string $scope, int $roomsPerSupervisor = 5): array
    {
        $rooms = $venue->rooms()
            ->get()
            ->sortBy(fn ($room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)
            ->values();

        if ($rooms->isEmpty()) {
            return ['proctors' => 0, 'examiners' => 0, 'supervisors' => 0];
        }

        if ($scope === 'all') {
            ExamAssignment::where('examination_school_id', $venue->id)
                ->whereIn('role', array_column(self::ROOM_ROLES, 'value'))
                ->update(['exam_room_id' => null]);
        }

        $proctors = $this->assignSequential($venue, $rooms, ExamRole::Proctor, $scope);
        $examiners = $this->assignSequential($venue, $rooms, ExamRole::RoomExaminer, $scope);
        $supervisors = $this->assignAnchored($venue, $rooms, $scope, $roomsPerSupervisor);

        return ['proctors' => $proctors, 'examiners' => $examiners, 'supervisors' => $supervisors];
    }

    public function clear(ExaminationSchool $venue): int
    {
        return ExamAssignment::where('examination_school_id', $venue->id)
            ->whereIn('role', array_column(self::ROOM_ROLES, 'value'))
            ->update(['exam_room_id' => null]);
    }

    private function pool(ExaminationSchool $venue, ExamRole $role, string $scope): Collection
    {
        return ExamAssignment::where('examination_school_id', $venue->id)
            ->where('role', $role->value)
            ->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value])
            ->when($scope === 'unfilled', fn ($q) => $q->whereNull('exam_room_id'))
            ->get()
            ->shuffle();
    }

    /** Room 1 gets the first shuffled person, room 2 the next, etc. Stops when the pool runs out. */
    private function assignSequential(ExaminationSchool $venue, Collection $rooms, ExamRole $role, string $scope): int
    {
        $pool = $this->pool($venue, $role, $scope);
        $filledRoomIds = $scope === 'unfilled' ? $this->filledRoomIds($venue, $role) : [];

        $assigned = 0;
        $index = 0;

        foreach ($rooms as $room) {
            if ($index >= $pool->count()) {
                break;
            }
            if ($scope === 'unfilled' && in_array($room->id, $filledRoomIds, true)) {
                continue;
            }

            $pool[$index]->update(['exam_room_id' => $room->id]);
            $index++;
            $assigned++;
        }

        return $assigned;
    }

    /** Each Supervising Examiner anchors the first room of their group of $roomsPerSupervisor. */
    private function assignAnchored(ExaminationSchool $venue, Collection $rooms, string $scope, int $roomsPerSupervisor): int
    {
        $pool = $this->pool($venue, ExamRole::SupervisingExaminer, $scope);
        $filledRoomIds = $scope === 'unfilled' ? $this->filledRoomIds($venue, ExamRole::SupervisingExaminer) : [];

        $assigned = 0;

        foreach ($pool as $index => $assignment) {
            $groupStart = $index * $roomsPerSupervisor;
            if ($groupStart >= $rooms->count()) {
                break;
            }

            $anchorRoom = $rooms[$groupStart];

            if ($scope === 'unfilled' && in_array($anchorRoom->id, $filledRoomIds, true)) {
                continue;
            }

            $assignment->update(['exam_room_id' => $anchorRoom->id]);
            $assigned++;
        }

        return $assigned;
    }

    private function filledRoomIds(ExaminationSchool $venue, ExamRole $role): array
    {
        return ExamAssignment::where('examination_school_id', $venue->id)
            ->where('role', $role->value)
            ->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value])
            ->whereNotNull('exam_room_id')
            ->pluck('exam_room_id')
            ->all();
    }
}
