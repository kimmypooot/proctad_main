<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Support\DesignationRegistry;
use Illuminate\Support\Collection;

/**
 * Shared per-venue room-staffing math (required/assigned counts, per-room
 * breakdown) used by both the Examinations "Assign Rooms" step and the
 * standalone "Manage Rooms" page, so the two can't silently drift out of
 * sync with each other or with StaffingRandomizer's own anchor-group logic.
 *
 * Which designations the grid staffs is data, not code: any designation with a
 * `rooms_per_slot` takes part, so a custom one appears here alongside Proctor,
 * Room Examiner and Supervising Examiner. `rooms_per_slot` is how many rooms
 * one person covers — 1 per room, or a group of N anchored at the group's first
 * room, which is the arrangement StaffingRandomizer produces for supervisors.
 */
class RoomStaffingCalculator
{
    // Matches StaffingRandomizer's default anchoring group size, and seeds the
    // Supervising Examiner's rooms_per_slot.
    public const ROOMS_PER_SUPERVISOR = 5;

    /**
     * The staffing designations may be passed in, which keeps this class a pure
     * function over its arguments — the unit tests cover the arithmetic edges
     * without booting the framework. Left null (how the container builds it),
     * they are read from the registry on first use, inside a request.
     *
     * @param  list<array{key: string, label: string, rooms_per_slot: int, is_anchored: bool}>|null  $designations
     */
    public function __construct(private ?array $designations = null) {}

    /** @return list<array{key: string, label: string, rooms_per_slot: int, is_anchored: bool}> */
    private function designations(): array
    {
        return $this->designations ??= DesignationRegistry::roomDesignations();
    }

    /**
     * The designations as they apply at one venue, with the venue's own
     * rooms-per-supervisor substituted in.
     *
     * A venue may staff supervisors in groups of 3 to 8 rooms rather than the
     * designation's default, and both the required counts and the anchor rows
     * have to agree with whatever StaffingRandomizer actually used — otherwise
     * supervisors appear against the wrong rooms and correctly staffed rooms
     * read as Incomplete.
     *
     * The override applies to every anchored designation. In practice that is
     * the Supervising Examiner alone; a second anchored designation would share
     * the venue's value.
     *
     * @return list<array{key: string, label: string, rooms_per_slot: int, is_anchored: bool}>
     */
    private function designationsFor(?int $roomsPerSupervisor): array
    {
        if ($roomsPerSupervisor === null) {
            return $this->designations();
        }

        return array_map(
            fn (array $designation) => $designation['is_anchored']
                ? [...$designation, 'rooms_per_slot' => $roomsPerSupervisor]
                : $designation,
            $this->designations(),
        );
    }

    /**
     * @param  Collection  $assignments  rows: ['id','role','exam_room_id','status','member_name']
     */
    public function stats(Collection $rooms, Collection $assignments, ?int $roomsPerSupervisor = null): array
    {
        $placed = $this->eligible($assignments)->whereNotNull('exam_room_id');

        $roomsCount = $rooms->count();
        $assignedByRole = $placed->countBy('role');

        $required = [];
        $assigned = [];

        foreach ($this->designationsFor($roomsPerSupervisor) as $designation) {
            $required[$designation['key']] = (int) ceil($roomsCount / $designation['rooms_per_slot']);
            $assigned[$designation['key']] = $assignedByRole->get($designation['key'], 0);
        }

        $requiredTotal = array_sum($required);
        // Counted from the designations the grid staffs rather than from every
        // placed assignment, so a duty that happens to carry a room id cannot
        // push the ratio past what the grid actually asked for.
        $assignedTotal = array_sum($assigned);

        return [
            'rooms_count' => $roomsCount,
            'total_capacity' => $rooms->sum('capacity'),
            'required' => $required,
            'assigned' => $assigned,
            'required_total' => $requiredTotal,
            'assigned_total' => $assignedTotal,
            'ratio' => $requiredTotal > 0 ? min(100, round(($assignedTotal / $requiredTotal) * 100)) : null,
        ];
    }

    /**
     * One row per room, carrying a slot per staffing designation.
     *
     * Every room in an anchored group is credited with that group's anchor-room
     * holder, since a single assignment covering N rooms only ever links to the
     * group's first room.
     *
     * Rows keep `proctor`, `room_examiner` and `supervising_examiner` (and their
     * `*_assignment_id`) alongside the `slots` list. Those aliases are what the
     * scanner's room grouping and the room-assignment export read, and those
     * three are exactly the designations that machinery is about — keeping the
     * alias is cheaper and safer than teaching every consumer the dynamic shape.
     *
     * @param  Collection  $assignments  rows: ['id','role','exam_room_id','status','member_name']
     */
    public function breakdown(Collection $rooms, Collection $assignments, ?int $roomsPerSupervisor = null): Collection
    {
        $eligible = $this->eligible($assignments);
        $sortedRooms = $rooms->sortBy(fn ($room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)->values();
        $designations = $this->designationsFor($roomsPerSupervisor);

        // Anchored designations are looked up by the room their assignment
        // links to, which is the first room of each group.
        $anchoredHolders = [];

        foreach ($designations as $designation) {
            if ($designation['is_anchored']) {
                $anchoredHolders[$designation['key']] = $eligible
                    ->where('role', $designation['key'])
                    ->keyBy('exam_room_id');
            }
        }

        return $sortedRooms->values()->map(function ($room, $index) use ($sortedRooms, $eligible, $designations, $anchoredHolders) {
            $slots = [];
            $anyAnchorRow = false;

            foreach ($designations as $designation) {
                $perSlot = max(1, $designation['rooms_per_slot']);
                $groupStart = intdiv($index, $perSlot) * $perSlot;
                $isAnchor = $index === $groupStart;

                if ($designation['is_anchored']) {
                    $anchorRoom = $sortedRooms->get($groupStart);
                    $holder = $anchorRoom ? $anchoredHolders[$designation['key']]->get($anchorRoom->id) : null;
                    $anyAnchorRow = $anyAnchorRow || $isAnchor;
                } else {
                    $holder = $eligible->first(
                        fn ($a) => $a['role'] === $designation['key'] && $a['exam_room_id'] === $room->id,
                    );
                }

                $slots[] = [
                    'key' => $designation['key'],
                    'label' => $designation['label'],
                    'member_name' => $holder['member_name'] ?? null,
                    'assignment_id' => $holder['id'] ?? null,
                    // Only the anchor row of a group may be edited; the other
                    // rows show the same person read-only.
                    'editable' => ! $designation['is_anchored'] || $isAnchor,
                ];
            }

            $byKey = collect($slots)->keyBy('key');

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'capacity' => $room->capacity,
                'designation' => $room->designation,
                'slots' => $slots,
                'proctor' => $byKey[ExamRole::Proctor->value]['member_name'] ?? null,
                'proctor_assignment_id' => $byKey[ExamRole::Proctor->value]['assignment_id'] ?? null,
                'room_examiner' => $byKey[ExamRole::RoomExaminer->value]['member_name'] ?? null,
                'room_examiner_assignment_id' => $byKey[ExamRole::RoomExaminer->value]['assignment_id'] ?? null,
                'supervising_examiner' => $byKey[ExamRole::SupervisingExaminer->value]['member_name'] ?? null,
                'supervising_examiner_assignment_id' => $byKey[ExamRole::SupervisingExaminer->value]['assignment_id'] ?? null,
                'is_supervisor_anchor' => $anyAnchorRow,
                // Complete when every staffing designation for this room is filled.
                'complete' => $slots !== [] && collect($slots)->every(fn (array $slot) => $slot['member_name'] !== null),
            ];
        })->values();
    }

    /** Assignments a declined/cancelled/expired response shouldn't count toward staffing — matches StaffingRandomizer::pool(). */
    public function eligible(Collection $assignments): Collection
    {
        return $assignments->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value]);
    }
}
