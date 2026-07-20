<?php

namespace App\Services;

use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\ExamRoom;
use Illuminate\Support\Collection;

/**
 * There is no stored "reports to" link between a Supervising Examiner and
 * the Room Examiners/Proctors they supervise — StaffingRandomizer only
 * anchors one Supervising Examiner per group of N consecutive rooms (N is a
 * randomizer input, never persisted). This reconstructs the grouping
 * positionally: rooms are bucketed between one Supervising Examiner's
 * anchor room and the next one's, in room-number order, within the venue.
 *
 * Best-effort — accurate when rooms were auto-assigned via the randomizer;
 * may not reflect reality if staffing was done manually room-by-room. The
 * evaluation form lets the respondent correct or replace entries regardless,
 * choosing from the venue's assigned roster.
 *
 * Returns a supervising examiner's group whether or not those people's
 * attendance has been confirmed yet — see the note in subordinatesFor().
 */
class SupervisionHierarchyResolver
{
    /** @return Collection<int, ExamAssignment> Room Examiner/Proctor assignments supervised by $supervisor. */
    public function subordinatesFor(ExamAssignment $supervisor): Collection
    {
        if ($supervisor->role !== ExamRole::SupervisingExaminer || ! $supervisor->exam_room_id) {
            return collect();
        }

        $rooms = $this->sortedRooms($supervisor->examination_school_id);

        $myPosition = $rooms->search(fn (ExamRoom $room) => $room->id === $supervisor->exam_room_id);
        if ($myPosition === false) {
            return collect();
        }

        $supervisors = ExamAssignment::query()
            ->where('examination_school_id', $supervisor->examination_school_id)
            ->where('role', ExamRole::SupervisingExaminer->value)
            ->whereNotNull('exam_room_id')
            ->get()
            ->map(fn (ExamAssignment $se) => [
                'assignment' => $se,
                'position' => $rooms->search(fn (ExamRoom $room) => $room->id === $se->exam_room_id),
            ])
            ->filter(fn (array $entry) => $entry['position'] !== false)
            ->sortBy('position')
            ->values();

        $myIndex = $supervisors->search(fn (array $entry) => $entry['assignment']->id === $supervisor->id);
        $nextPosition = $myIndex !== false && $myIndex + 1 < $supervisors->count()
            ? $supervisors[$myIndex + 1]['position']
            : $rooms->count();

        $bucketRoomIds = $rooms->slice($myPosition, $nextPosition - $myPosition)->pluck('id');

        // Not filtered on confirmed attendance, matching the ratee list the form
        // offers. CSC examinations are half-day, so a supervising examiner is
        // usually evaluating while the secretariat is still scanning — filtering
        // here meant the inference returned nobody even when the room grouping
        // was resolved correctly, and the respondent was asked to pick a roster
        // the system had already worked out.
        //
        // Someone assigned to a room in this group is the right default. The
        // respondent reviews every row and can remove anyone who did not serve,
        // and unconfirmed attendance is labelled in the picker either way.
        return ExamAssignment::query()
            ->whereIn('exam_room_id', $bucketRoomIds)
            ->whereIn('role', [ExamRole::RoomExaminer->value, ExamRole::Proctor->value])
            ->with('member', 'room')
            ->get();
    }

    /** @return Collection<int, ExamRoom> */
    private function sortedRooms(int $examinationSchoolId): Collection
    {
        return ExamRoom::query()
            ->where('examination_school_id', $examinationSchoolId)
            ->get()
            ->sortBy(fn (ExamRoom $room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)
            ->values();
    }
}
