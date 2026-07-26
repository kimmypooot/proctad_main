<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The preflight exists to catch failures that look like success, so it has to be
 * trustworthy in both directions: it must fail a host that is not ready, and it
 * must not cry wolf on one that is.
 */
class PreflightTest extends TestCase
{
    use RefreshDatabase;

    /** A host configured the way production should be. */
    private function productionLikeConfig(): void
    {
        config([
            'app.debug' => false,
            'app.url' => 'https://proctad.cscro8.test',
            'mail.default' => 'smtp',
            'queue.default' => 'database',
            // Transport settings whose absence looks like nothing at all: the
            // site works either way, and the session cookie travels in the
            // clear. The preflight fails a host without them, so a ready host
            // has to declare them here too.
            'session.secure' => true,
            'session.encrypt' => true,
            'security.trusted_hosts' => ['proctad.cscro8.test'],
            'security.trusted_proxies' => ['10.0.0.0/8'],
            'security.csp_report_only' => false,
            // Not the key that was published in .env.example.
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            // The development checkout still holds the legacy dumps this check
            // exists to catch, so point it somewhere empty; the check itself is
            // exercised by test_it_fails_when_a_database_dump_is_present.
            'security.dump_scan_paths' => [$this->emptyScanPath()],
        ]);
    }

    /** A directory guaranteed to contain no .sql files. */
    private function emptyScanPath(): string
    {
        $path = storage_path('framework/testing/preflight-scan');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    public function test_it_fails_when_debug_is_enabled(): void
    {
        $this->productionLikeConfig();
        config(['app.debug' => true]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('APP_DEBUG')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_mail_is_only_written_to_the_log(): void
    {
        $this->productionLikeConfig();
        config(['mail.default' => 'log']);

        $this->artisan('proctad:preflight')->assertExitCode(1);
    }

    /** Emailed links and OAuth redirects derive from APP_URL. */
    public function test_it_fails_on_a_localhost_app_url(): void
    {
        $this->productionLikeConfig();
        config(['app.url' => 'http://127.0.0.1:8001']);

        $this->artisan('proctad:preflight')->assertExitCode(1);
    }

    /**
     * UserSeeder creates six accounts including a Super Administrator, all with
     * the password "password". Reaching production with those is the breach.
     */
    public function test_it_fails_when_seeded_test_accounts_are_present(): void
    {
        $this->productionLikeConfig();

        User::factory()->create(['email' => 'superadmin@proctad.test', 'role' => UserRole::SuperAdmin]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('Seeded test accounts')
            ->assertExitCode(1);
    }

    /**
     * The silent failure this command was written for: assignments are created,
     * the page reports success, and the confirmations are never sent because
     * nothing is draining the queue.
     */
    public function test_it_fails_when_queued_jobs_are_going_stale(): void
    {
        $this->productionLikeConfig();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->getTimestamp(),
            'created_at' => now()->subHour()->getTimestamp(),
        ]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('Queue worker')
            ->assertExitCode(1);
    }

    /** A recently queued job is a worker mid-flight, not a stalled queue. */
    public function test_it_passes_when_queued_jobs_are_recent(): void
    {
        $this->productionLikeConfig();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->artisan('proctad:preflight')->assertExitCode(0);
    }

    /** Warnings are advisory and must not block a deployment on their own. */
    public function test_maintenance_mode_warns_but_does_not_fail(): void
    {
        $this->productionLikeConfig();
        Setting::set('site_maintenance_mode', true, 'boolean');

        $this->artisan('proctad:preflight')->assertExitCode(0);
    }

    public function test_a_ready_host_passes(): void
    {
        $this->productionLikeConfig();

        $this->artisan('proctad:preflight')->assertExitCode(0);
    }

    /**
     * The session cookie is the credential for the whole console. Without the
     * Secure flag it is sent over plain HTTP on any downgrade — a stray link, a
     * captive portal, an SSL-stripping proxy on venue wifi.
     */
    public function test_it_fails_when_the_session_cookie_is_not_secure(): void
    {
        $this->productionLikeConfig();
        config(['session.secure' => false]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE')
            ->assertExitCode(1);
    }

    /**
     * An unchecked Host header lets an attacker have a password reset link for
     * someone else's account delivered to a domain they control.
     */
    public function test_it_fails_when_no_trusted_hosts_are_configured(): void
    {
        $this->productionLikeConfig();
        config(['security.trusted_hosts' => []]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('TRUSTED_HOSTS')
            ->assertExitCode(1);
    }

    /**
     * The key published in .env.example is known to anyone who has ever cloned
     * the repository, and it signs every signed URL and encrypts members' dates
     * of birth.
     */
    public function test_it_fails_on_the_app_key_that_was_committed(): void
    {
        $this->productionLikeConfig();
        config(['app.key' => 'base64:gd0SfSBxVCRazK+ffmGw4JVxmeLK0GDx/ucxYUvh/0s=']);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('APP_KEY')
            ->assertExitCode(1);
    }

    /**
     * The legacy import shared one password across every account, and its
     * hashes were committed. An account carried across that has not since set a
     * new password is holding a credential an attacker can derive offline.
     */
    public function test_it_fails_when_legacy_accounts_still_hold_their_old_password(): void
    {
        $this->productionLikeConfig();

        User::factory()->create([
            'email' => 'legacy@csc.gov.ph',
            'username' => 'legacy',
            'must_change_password' => false,
        ]);

        $this->artisan('proctad:preflight')
            ->expectsOutputToContain('Legacy credentials')
            ->assertExitCode(1);
    }

    /**
     * A dump on the live host is the whole registry in one downloadable file if
     * the document root is ever misconfigured — and the legacy one carried
     * password hashes.
     */
    public function test_it_fails_when_a_database_dump_is_present(): void
    {
        $this->productionLikeConfig();

        $path = $this->emptyScanPath().DIRECTORY_SEPARATOR.'stray-dump.sql';
        file_put_contents($path, '-- dump');

        try {
            $this->artisan('proctad:preflight')
                ->expectsOutputToContain('Database dumps')
                ->assertExitCode(1);
        } finally {
            @unlink($path);
        }
    }

    /** Read-only: a check that mutates the host it is inspecting is not a check. */
    public function test_it_changes_nothing(): void
    {
        $this->productionLikeConfig();

        User::factory()->create(['email' => 'staff@csc.gov.ph', 'role' => UserRole::EsdAdmin]);
        $before = [User::count(), DB::table('jobs')->count(), Setting::count()];

        $this->artisan('proctad:preflight');

        $this->assertSame($before, [User::count(), DB::table('jobs')->count(), Setting::count()]);
    }
}
