<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Models\ExamAssignment;
use Illuminate\Console\Command;

/**
 * Marks assignment confirmations expired once their 7-day signed link window
 * has passed without a response.
 */
class ExpirePendingAssignments extends Command
{
    protected $signature = 'proctad:expire-pending-assignments';

    protected $description = 'Mark unanswered assignment confirmations as expired after 7 days';

    public function handle(): int
    {
        $stale = ExamAssignment::query()
            ->where('status', AssignmentStatus::Pending)
            ->whereNotNull('confirmation_sent_at')
            ->where('confirmation_sent_at', '<=', now()->subDays(7))
            ->get();

        foreach ($stale as $assignment) {
            $assignment->update(['status' => AssignmentStatus::Expired]);
            $assignment->confirmations()->create(['action' => ConfirmationAction::Expired]);
        }

        $this->info("Expired {$stale->count()} assignment(s).");

        return self::SUCCESS;
    }
}
