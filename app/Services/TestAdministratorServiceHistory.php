<?php

namespace App\Services;

use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\Member;

/**
 * Shared "Test Administrator" service-history lookup — used by the
 * Service History report (ServiceHistoryController) and by the QR
 * scanner, which surfaces the same record at the moment a Test
 * Administrator is scanned during an examination.
 */
class TestAdministratorServiceHistory
{
    /** Exam-day designations tracked as "Test Administrator" service. */
    public const ROLES = [
        ExamRole::ChiefExaminer,
        ExamRole::SupervisingExaminer,
        ExamRole::Proctor,
        ExamRole::RoomExaminer,
    ];

    public static function forMember(Member $member): array
    {
        $roleValues = array_column(self::ROLES, 'value');

        $history = $member->assignments()
            ->whereIn('role', $roleValues)
            ->whereNotNull('attendance_confirmed_at')
            ->with([
                'examination:id,title,type,exam_date',
                'examinationSchool.school:id,name',
                'room:id,room_number,designation',
                'coveringFor.member:id,first_name,middle_name,last_name,suffix',
            ])
            ->get()
            ->sortByDesc(fn (ExamAssignment $assignment) => $assignment->examination?->exam_date)
            ->values();

        $byRole = $history->countBy(fn (ExamAssignment $assignment) => $assignment->role->value);

        return [
            'total_served' => $history->count(),
            'designations' => collect(self::ROLES)
                ->map(fn (ExamRole $role) => [
                    'label' => $role->label(),
                    'count' => $byRole->get($role->value, 0),
                ])
                ->filter(fn (array $designation) => $designation['count'] > 0)
                ->values(),
            'records' => $history->map(fn (ExamAssignment $assignment) => [
                'id' => $assignment->id,
                'exam_title' => $assignment->examination?->title,
                'exam_type' => $assignment->examination?->type,
                'exam_date' => $assignment->examination?->exam_date?->format('M d, Y'),
                'role_label' => $assignment->role->label(),
                'status_label' => $assignment->status->label(),
                'status_variant' => $assignment->status->badgeVariant(),
                'testing_center' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room
                    ? trim("{$assignment->room->designation} {$assignment->room->room_number}")
                    : null,
                'attendance_confirmed_at' => $assignment->attendance_confirmed_at?->format('M d, Y g:i A'),
                // Service rendered as a called-in reserve still counts toward
                // the totals above — it was the same day's work — but the row
                // says so, since the designation alone no longer reveals it.
                'service_note' => $assignment->serviceNote(),
            ])->values(),
        ];
    }
}
