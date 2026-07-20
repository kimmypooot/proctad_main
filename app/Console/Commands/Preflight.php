<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deployment readiness check for the production host.
 *
 * Turns the prose in CUTOVER_RUNBOOK.md §1 and §4 into something executable, and
 * concentrates on the failures that look exactly like success: a missing queue
 * worker means bulk assignment confirmations are never sent while the UI reports
 * "assigned"; a missing cron entry means reminders never fire and nothing
 * reports an error; MAIL_MAILER=log means every email is silently written to a
 * file. None of these surface on their own.
 *
 * Read-only — it inspects and reports, and changes nothing.
 */
class Preflight extends Command
{
    protected $signature = 'proctad:preflight';

    protected $description = 'Check production deployment prerequisites: config, cron, queue worker, seeded accounts, storage';

    /** @var array<int, array{0: string, 1: string, 2: string}> status, check, detail */
    private array $results = [];

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->configuration();
        $this->scheduler();
        $this->queueWorker();
        $this->seededAccounts();
        $this->storageAndDatabase();
        $this->operationalSettings();

        $this->newLine();
        $this->table(['', 'Check', 'Detail'], $this->results);

        if ($this->failures > 0) {
            $this->newLine();
            $this->error("{$this->failures} check(s) failed. Do not open access to real users until these are resolved.");

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->newLine();
            $this->warn("{$this->warnings} warning(s). Review them, then proceed.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function recordPass(string $check, string $detail): void
    {
        $this->results[] = ['PASS', $check, $detail];
    }

    private function recordWarn(string $check, string $detail): void
    {
        $this->warnings++;
        $this->results[] = ['WARN', $check, $detail];
    }

    private function recordFail(string $check, string $detail): void
    {
        $this->failures++;
        $this->results[] = ['FAIL', $check, $detail];
    }

    private function configuration(): void
    {
        app()->environment('production')
            ? $this->recordPass('APP_ENV', 'production')
            : $this->recordWarn('APP_ENV', 'Currently "'.app()->environment().'" — expected "production" on the live host.');

        // The one that leaks: with debug on, any error page exposes stack
        // traces, file paths and config values to whoever triggered it.
        config('app.debug')
            ? $this->recordFail('APP_DEBUG', 'Enabled. Error pages will expose stack traces and configuration.')
            : $this->recordPass('APP_DEBUG', 'Disabled');

        config('mail.default') === 'log'
            ? $this->recordFail('MAIL_MAILER', 'Set to "log" — mail is written to storage/logs and never delivered.')
            : $this->recordPass('MAIL_MAILER', config('mail.default'));

        $url = (string) config('app.url');
        str_contains($url, '127.0.0.1') || str_contains($url, 'localhost')
            ? $this->recordFail('APP_URL', "{$url} — Google OAuth redirects and emailed links derive from this.")
            : $this->recordPass('APP_URL', $url);

        config('app.key')
            ? $this->recordPass('APP_KEY', 'Set')
            : $this->recordFail('APP_KEY', 'Missing — encrypted columns (including date_of_birth) cannot be read.');
    }

    private function scheduler(): void
    {
        // Registration is a code fact and always true; whether cron actually
        // invokes schedule:run is not observable from inside the application,
        // so this reports what needs checking on the host rather than guessing.
        $expected = ['proctad:send-assignment-reminders', 'proctad:expire-pending-assignments', 'proctad:prune-logs'];
        $registered = collect($this->getApplication()?->all() ?? [])->keys();

        $missing = array_values(array_filter($expected, fn (string $c) => ! $registered->contains($c)));

        $missing === []
            ? $this->recordPass('Scheduled commands', implode(', ', $expected))
            : $this->recordFail('Scheduled commands', 'Not registered: '.implode(', ', $missing));

        $this->recordWarn(
            'Cron entry',
            'Cannot be verified from here. Confirm on the host: * * * * * cd '.base_path().' && php artisan schedule:run',
        );
    }

    private function queueWorker(): void
    {
        if (config('queue.default') === 'sync') {
            // Not a failure: sync runs jobs inline, so nothing is lost. But it
            // puts the SMTP round-trips back inside the request that queueing
            // moved out, which is what times out on a large bulk assignment.
            $this->recordWarn('QUEUE_CONNECTION', 'sync — assignment confirmations will send inside the request again.');

            return;
        }

        $this->recordPass('QUEUE_CONNECTION', (string) config('queue.default'));

        if (! Schema::hasTable('jobs')) {
            $this->recordFail('Queue table', 'The "jobs" table is missing; queued confirmations cannot be stored.');

            return;
        }

        $pending = DB::table('jobs')->count();
        $stale = DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->getTimestamp())->count();

        if ($stale > 0) {
            // The silent failure this command exists for: assignments are
            // created, the page reports success, and nobody is ever emailed.
            $this->recordFail(
                'Queue worker',
                "{$stale} job(s) older than 15 minutes are still queued — no worker appears to be running.",
            );
        } elseif ($pending > 0) {
            $this->recordPass('Queue worker', "{$pending} job(s) queued and recent — a worker appears to be draining them.");
        } else {
            $this->recordWarn(
                'Queue worker',
                'No queued jobs to observe. Run a bulk assignment, then re-run this to confirm the queue drains.',
            );
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $failed > 0
                ? $this->recordWarn('Failed jobs', "{$failed} entr(ies) in failed_jobs — inspect with queue:failed.")
                : $this->recordPass('Failed jobs', 'None');
        }
    }

    private function seededAccounts(): void
    {
        // UserSeeder creates six accounts, including a Super Administrator, all
        // with the password "password". In production that is the breach.
        $seeded = User::where('email', 'like', '%@proctad.test')->pluck('email');

        $seeded->isEmpty()
            ? $this->recordPass('Seeded test accounts', 'None present')
            : $this->recordFail('Seeded test accounts', $seeded->count().' found — delete before go-live: '.$seeded->take(3)->implode(', ').($seeded->count() > 3 ? ' …' : ''));
    }

    private function storageAndDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->recordPass('Database', 'Connected to '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $this->recordFail('Database', 'Cannot connect: '.$e->getMessage());

            return;
        }

        try {
            // Member photos, requirement documents and generated PDFs all land
            // on this disk; an unwritable one fails at upload time, per user.
            $probe = 'preflight-'.uniqid().'.txt';
            Storage::disk('local')->put($probe, 'ok');
            Storage::disk('local')->delete($probe);
            $this->recordPass('Local storage', 'Writable');
        } catch (Throwable $e) {
            $this->recordFail('Local storage', 'Not writable: '.$e->getMessage());
        }
    }

    private function operationalSettings(): void
    {
        Setting::get('site_maintenance_mode', false)
            ? $this->recordWarn('Maintenance mode', 'ON — the site is closed to members and the public.')
            : $this->recordPass('Maintenance mode', 'Off');

        Setting::emailSendingEnabled()
            ? $this->recordPass('Email sending', 'Enabled in Settings')
            : $this->recordWarn('Email sending', 'Switched off in Settings — nothing will be delivered.');
    }
}
