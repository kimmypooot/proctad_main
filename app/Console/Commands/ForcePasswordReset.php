<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Flags accounts to change their password at next sign-in.
 *
 * Written for one specific job: a legacy database export committed to the
 * repository carried 61 bcrypt hashes, every one of them identical — the whole
 * legacy estate shared a single password. Anyone with the repository can crack
 * that one hash offline, with no rate limit and no lockout, and then try it
 * against every account that was carried across the migration.
 *
 * Changing the password is the only thing that helps; rotating the repository
 * does not, because the hashes are already out. EnsurePasswordIsChanged already
 * enforces the gate — this just raises the flag on the right accounts.
 *
 * Google-only accounts are skipped: they have no usable password to change
 * (registration sets a random 40-character value), so flagging them would
 * strand them on a form they cannot complete.
 */
class ForcePasswordReset extends Command
{
    protected $signature = 'proctad:force-password-reset
                            {--legacy : Only accounts carried across from the legacy system (those with a username)}
                            {--role=* : Limit to specific roles, e.g. --role=fo_admin --role=esd_admin}
                            {--dry-run : Report what would change, and change nothing}';

    protected $description = 'Require selected users to set a new password at next sign-in';

    public function handle(): int
    {
        $query = User::query()
            ->where('must_change_password', false)
            // No password to change. Signing in happens through Google.
            ->whereNull('google_id');

        if ($this->option('legacy')) {
            // The legacy import is the only source of username-based accounts;
            // self-registration is Google-only and sets no username.
            $query->whereNotNull('username');
        }

        if ($roles = $this->option('role')) {
            $query->whereIn('role', array_map(
                fn (string $role) => UserRole::from($role)->value,
                $roles,
            ));
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No accounts match — nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Email', 'Username', 'Role'],
                (clone $query)->limit(20)->get()
                    ->map(fn (User $user) => [$user->email, $user->username, $user->role->value])
                    ->all(),
            );
            $this->info("{$count} account(s) would be flagged. Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Require {$count} account(s) to change their password at next sign-in?", true)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $affected = DB::transaction(function () use ($query) {
            $users = (clone $query)->get(['id', 'field_office_id']);

            (clone $query)->update(['must_change_password' => true]);

            // Recorded per account rather than as one line, so the audit trail
            // can answer "why was I asked to change my password" for any
            // individual who asks.
            foreach ($users as $user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'password_change_required',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'field_office_id' => $user->field_office_id,
                    'changes' => ['reason' => 'Credential exposure remediation (SEC-02)'],
                ]);
            }

            return $users->count();
        });

        $this->info("{$affected} account(s) flagged. They will be redirected to /change-password at next sign-in.");
        $this->warn('Tell the affected users this is coming, or the first they know of it is a forced prompt.');

        return self::SUCCESS;
    }
}
