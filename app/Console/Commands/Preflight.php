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
        $this->exposedFiles();
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

        // Wrong here and nothing errors — every timestamp is simply eight hours
        // out. An attendance scan taken at 8:00 AM reads back as midnight, and
        // `today()` sits on the previous day right through exam morning.
        config('app.timezone') === 'Asia/Manila'
            ? $this->recordPass('APP_TIMEZONE', 'Asia/Manila')
            : $this->recordFail('APP_TIMEZONE', config('app.timezone').' — Region VIII runs on Philippine Standard Time (Asia/Manila). Every displayed timestamp will be off.');

        config('app.key')
            ? $this->recordPass('APP_KEY', 'Set')
            : $this->recordFail('APP_KEY', 'Missing — encrypted columns (including date_of_birth) cannot be read.');

        // Published in .env.example before it was sanitised, so it is in the
        // git history and must be treated as known to anyone with the repo.
        config('app.key') === 'base64:gd0SfSBxVCRazK+ffmGw4JVxmeLK0GDx/ucxYUvh/0s='
            ? $this->recordFail('APP_KEY', 'This is the key that was committed to .env.example. Anyone with the repository can forge signed URLs and decrypt PII. Rotate it.')
            : $this->recordPass('APP_KEY origin', 'Not the previously committed value');

        $this->transportSecurity();
    }

    /**
     * The settings whose absence looks like nothing at all: the site works, the
     * pages load, and the session cookie is travelling in the clear.
     */
    private function transportSecurity(): void
    {
        config('session.secure')
            ? $this->recordPass('SESSION_SECURE_COOKIE', 'Enabled')
            : $this->recordFail('SESSION_SECURE_COOKIE', 'Not set — the session cookie will be sent over plain HTTP on any downgrade.');

        config('session.encrypt')
            ? $this->recordPass('SESSION_ENCRYPT', 'Enabled')
            : $this->recordWarn('SESSION_ENCRYPT', 'Disabled — session payloads are stored unencrypted.');

        // Not merely a hardening nicety: without trusted proxies behind a load
        // balancer, request()->ip() is the proxy for everyone, so the login
        // throttle and every other limiter share one bucket, and audit_logs
        // records the proxy address for every event.
        config('security.trusted_proxies')
            ? $this->recordPass('TRUSTED_PROXIES', implode(', ', (array) config('security.trusted_proxies')))
            : $this->recordWarn('TRUSTED_PROXIES', 'Not set. If the app is behind a proxy, rate limiting and audited IPs are both wrong, and Secure cookies are suppressed.');

        config('security.trusted_hosts')
            ? $this->recordPass('TRUSTED_HOSTS', implode(', ', (array) config('security.trusted_hosts')))
            : $this->recordFail('TRUSTED_HOSTS', 'Not set — an attacker can set the Host header and have password reset links delivered to a domain they control.');

        str_starts_with((string) config('app.url'), 'https://')
            ? $this->recordPass('HTTPS', 'APP_URL is https')
            : $this->recordFail('HTTPS', 'APP_URL is not https — emailed links and OAuth redirects will be generated as plain HTTP.');

        config('security.csp_report_only')
            ? $this->recordWarn('Content-Security-Policy', 'Report-only. Enforce it once one exam cycle has passed with no violations.')
            : $this->recordPass('Content-Security-Policy', 'Enforcing');
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

        // The legacy estate shared one password across every account, and its
        // hashes were committed to the repository. Any account carried across
        // that has not since set a new password is holding a credential an
        // attacker can derive offline.
        $unreset = User::whereNotNull('username')
            ->whereNull('google_id')
            ->where('must_change_password', false)
            ->count();

        $unreset === 0
            ? $this->recordPass('Legacy credentials', 'No un-reset legacy accounts')
            : $this->recordFail('Legacy credentials', "{$unreset} legacy account(s) still hold a pre-migration password. Run: php artisan proctad:force-password-reset --legacy");
    }

    /**
     * Files that must never have been in the repository, checked on the host in
     * case a deployment copied the working tree wholesale.
     */
    private function exposedFiles(): void
    {
        $dumps = [];

        foreach ((array) config('security.dump_scan_paths', []) as $path) {
            $dumps = array_merge($dumps, glob(rtrim($path, '\\/').DIRECTORY_SEPARATOR.'*.sql') ?: []);
        }

        $dumps === []
            ? $this->recordPass('Database dumps', 'None in the project root or storage/')
            : $this->recordFail('Database dumps', count($dumps).' found — remove from the host: '.implode(', ', array_map('basename', $dumps)));

        // Only reachable if the document root is the project root rather than
        // public/, which would also expose .env. Worth saying plainly.
        file_exists(public_path('.env'))
            ? $this->recordFail('Environment file', '.env is inside public/ — it is downloadable.')
            : $this->recordPass('Environment file', 'Not web-accessible from public/');
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
