<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Models\ExamAssignment;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Reminds members who haven't responded 3+ days after the confirmation
 * request, once, before the 7-day link expires (see AssignmentConfirmationController).
 */
class SendAssignmentReminders extends Command
{
    protected $signature = 'proctad:send-assignment-reminders';

    protected $description = 'Send a reminder email for pending assignment confirmations older than 3 days';

    public function handle(NotificationMailer $mailer): int
    {
        $candidates = ExamAssignment::query()
            ->with('member', 'examination')
            ->where('status', AssignmentStatus::Pending)
            ->whereNotNull('confirmation_sent_at')
            ->where('confirmation_sent_at', '<=', now()->subDays(3))
            ->where('confirmation_sent_at', '>', now()->subDays(7))
            ->whereDoesntHave('confirmations', fn ($q) => $q->where('action', ConfirmationAction::ReminderSent))
            ->get();

        $sent = 0;

        foreach ($candidates as $assignment) {
            if (! $assignment->member?->email) {
                continue;
            }

            $signedUrl = URL::temporarySignedRoute(
                'assignments.confirm',
                $assignment->confirmation_sent_at->addDays(7),
                ['assignment' => $assignment->id],
            );

            $mailer->send(
                templateCode: 'assignment_reminder',
                toEmail: $assignment->member->email,
                toName: $assignment->member->name,
                emailType: 'designation',
                data: [
                    'member_name' => $assignment->member->name,
                    'exam_name' => $assignment->examination->title,
                    'exam_date' => $assignment->examination->exam_date->format('F j, Y (l)'),
                    'designation' => $assignment->role->label(),
                    'proctad_id' => $assignment->member->proctad_id,
                    'confirmation_url' => $signedUrl,
                ],
            );

            $assignment->confirmations()->create(['action' => ConfirmationAction::ReminderSent]);
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
