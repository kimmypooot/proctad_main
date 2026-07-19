<?php

namespace App\Jobs;

use App\Models\ExamAssignment;
use App\Models\User;
use App\Services\AssignmentConfirmationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one assignment confirmation off the request cycle.
 *
 * Queued for batch deployments only (ExamAssignmentController::bulkStore),
 * where staffing a full venue means one SMTP round-trip per member and the
 * request would otherwise run until it times out, leaving the batch half sent
 * with no record of where it stopped. Single sends stay synchronous so the
 * "Send Confirmation" button can still report a bounce inline.
 *
 * The job wraps the whole send-and-bookkeep operation rather than just the
 * mail, deliberately: AssignmentConfirmationSender only stamps
 * confirmation_sent_at when delivery actually succeeded, and queueing only the
 * mail would leave the caller looking at a 'queued' status it cannot interpret,
 * stamping the assignment optimistically. A member whose email then failed for
 * good would look asked, and the reminder command would skip them.
 */
class SendAssignmentConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $assignmentId,
        private readonly ?int $sentById = null,
        /** Captured at dispatch: there is no request in the worker. */
        private readonly ?string $ipAddress = null,
    ) {}

    public function handle(AssignmentConfirmationSender $sender): void
    {
        $assignment = ExamAssignment::find($this->assignmentId);

        // Deleted between dispatch and running — nothing to confirm.
        if ($assignment === null) {
            return;
        }

        $sender->send(
            $assignment,
            $this->sentById === null ? null : User::find($this->sentById),
            $this->ipAddress,
        );
    }
}
