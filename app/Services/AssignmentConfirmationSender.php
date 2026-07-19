<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Models\EmailLog;
use App\Models\ExamAssignment;
use App\Models\User;
use App\Notifications\VenueAssigned;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Emails a member a signed confirmation link for their exam assignment, and
 * (if they have a linked self-service account) an in-app bell notification
 * too — the single place both the automatic "venue assigned" trigger
 * (ExamAssignmentController) and the manual "Send/Resend Confirmation" button
 * (AssignmentConfirmationController) funnel through, so the two can't drift
 * out of sync with each other.
 */
class AssignmentConfirmationSender
{
    /** How long a confirmation link stays valid (matches the legacy 7-day token). */
    private const LINK_LIFETIME_DAYS = 7;

    public function __construct(private readonly NotificationMailer $mailer) {}

    /**
     * @return EmailLog|null the delivery attempt's log row, or null if the member
     *                       has no email on file. Callers that report back to a
     *                       user must check `$log->status` — a returned log can
     *                       still be a 'failed' one.
     */
    public function send(ExamAssignment $assignment, ?User $sentBy = null): ?EmailLog
    {
        $assignment->loadMissing('member.user', 'examination');
        $member = $assignment->member;

        if (! $member?->email) {
            return null;
        }

        $signedUrl = URL::temporarySignedRoute(
            'assignments.confirm',
            now()->addDays(self::LINK_LIFETIME_DAYS),
            ['assignment' => $assignment->id],
        );

        $log = $this->mailer->send(
            templateCode: 'assignment_confirmation',
            toEmail: $member->email,
            toName: $member->name,
            emailType: 'designation',
            data: [
                'member_name' => $member->nameFirstLast(),
                'exam_name' => $assignment->examination->title,
                'exam_date' => $assignment->examination->exam_date->format('F j, Y (l)'),
                'designation' => $assignment->role->label(),
                'proctad_id' => $member->proctad_id,
                'confirmation_url' => $signedUrl,
            ],
            sentBy: $sentBy,
        );

        // Nothing left the building, so don't record that it did: stamping
        // confirmation_sent_at on a failure would flip the button to "Resend",
        // reset a existing response, and make the reminder command think the
        // member has already been asked.
        if ($log->status === 'failed') {
            return $log;
        }

        $assignment->update([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now(),
            'responded_at' => null,
            'decline_reason' => null,
        ]);

        $assignment->confirmations()->create([
            'action' => ConfirmationAction::Sent,
            'ip_address' => request()->ip(),
            'metadata' => ['sent_by' => $sentBy?->id],
        ]);

        // Members without a linked self-service account (no login) simply
        // don't get the in-app half — the email is the one channel every
        // member has, regardless of whether they've ever logged in.
        if ($member->user) {
            Notification::send([$member->user], new VenueAssigned($assignment));
        }

        return $log;
    }
}
