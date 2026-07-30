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
     * How a member's answer reached the office, when staff record it for them.
     *
     * Kept here rather than in the controller so the form, the validation rule
     * and the label written into the audit row cannot disagree.
     */
    public const ON_BEHALF_CHANNELS = [
        'phone' => 'Phone call',
        'sms' => 'Text message',
        'in_person' => 'In person',
        'relayed' => 'Relayed by their Field Office',
        'other' => 'Other',
    ];

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
        return $this->record($assignment, $confirmed, $declineReason, [
            'ip_address' => $ipAddress,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
            'metadata' => $confirmed ? null : ['decline_reason' => $declineReason],
        ]);
    }

    /**
     * The answer a member gave off-system, recorded by staff on their behalf.
     *
     * Testing centers are often rural and the confirmation link expires in
     * seven days, so an appreciable number of members answer by phone or in
     * person instead — previously the office could only flip the status, which
     * left a confirmation indistinguishable from one the member made and no
     * record of who took the call.
     *
     * Same one-shot rule and same decline notification as a member's own
     * response: this records an answer, it does not overrule one already given.
     *
     * @return bool false when the assignment was not pending
     */
    public function recordOnBehalf(
        ExamAssignment $assignment,
        User $staff,
        bool $confirmed,
        ?string $declineReason = null,
        string $channel = 'other',
        ?string $note = null,
        ?string $ipAddress = null,
    ): bool {
        return $this->record($assignment, $confirmed, $declineReason, [
            'ip_address' => $ipAddress,
            'metadata' => [
                'on_behalf' => true,
                'recorded_by' => $staff->id,
                // Denormalised so the assignments table can name the recorder
                // without a join, and so the trail survives the account being
                // renamed or deleted.
                'recorded_by_name' => $staff->name,
                'channel' => $channel,
                'channel_label' => self::ON_BEHALF_CHANNELS[$channel] ?? $channel,
                'note' => $note,
                'decline_reason' => $confirmed ? null : $declineReason,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $confirmationAttributes
     */
    private function record(
        ExamAssignment $assignment,
        bool $confirmed,
        ?string $declineReason,
        array $confirmationAttributes,
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
            ...$confirmationAttributes,
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
