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
        ]);
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
