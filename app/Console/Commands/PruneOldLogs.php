<?php

namespace App\Console\Commands;

use App\Models\AssignmentConfirmation;
use App\Models\AuditLog;
use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention sweep for high-volume append-only logs. Audit logs and
 * assignment confirmations are kept 2 years (spec/compliance trail);
 * email logs and read in-app notifications are operational noise, kept
 * 6 months / 90 days respectively.
 */
class PruneOldLogs extends Command
{
    protected $signature = 'proctad:prune-logs';

    protected $description = 'Delete old audit logs, email logs, assignment confirmations, and read notifications past their retention window';

    public function handle(): int
    {
        $auditLogs = AuditLog::where('created_at', '<', now()->subYears(2))->delete();
        $confirmations = AssignmentConfirmation::where('created_at', '<', now()->subYears(2))->delete();
        $emailLogs = EmailLog::where('created_at', '<', now()->subMonths(6))->delete();
        $notifications = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays(90))
            ->delete();

        $this->info("Pruned: {$auditLogs} audit log(s), {$confirmations} assignment confirmation(s), {$emailLogs} email log(s), {$notifications} read notification(s).");

        return self::SUCCESS;
    }
}
