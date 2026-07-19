<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\User;
use App\Notifications\AssignmentDeclined;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Records a member's confirm/decline response to an exam assignment.
 *
 * Extracted so the two entry points cannot drift apart: the signed link from
 * the confirmation email (open to members with no password, since the
 * signature is the credential) and the signed-in "My Assignments" page. Both
 * must apply the same one-shot rule, write the same audit row, and raise the
 * same re-staffing notification on a decline.
 */
class AssignmentResponder
{
    /**
     * @return bool false when the assignment was not pending — responses are
     *              deliberately one-shot, so a second attempt changes nothing.
     */
    public function respond(
        ExamAssignment $assignment,
        bool $confirmed,
        ?string $declineReason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): bool {
        if ($assignment->status !== AssignmentStatus::Pending) {
            return false;
        }

        $assignment->update([
            'status' => $confirmed ? AssignmentStatus::Confirmed : AssignmentStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $confirmed ? null : $declineReason,
        ]);

        $assignment->confirmations()->create([
            'action' => $confirmed ? ConfirmationAction::Confirmed : ConfirmationAction::Declined,
            'ip_address' => $ipAddress,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
            'metadata' => $confirmed ? null : ['decline_reason' => $declineReason],
        ]);

        if (! $confirmed) {
            $this->notifyFieldOffice($assignment);
        }

        return true;
    }

    /**
     * Bell-notify the owning field office (fo_admin + field_director) that a
     * declined assignment needs re-staffing.
     */
    private function notifyFieldOffice(ExamAssignment $assignment): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::FoAdmin, UserRole::FieldDirector])
            ->where('field_office_id', $assignment->field_office_id)
            ->get();

        Notification::send($recipients, new AssignmentDeclined($assignment));
    }
}
