<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Public scanner links: budget per link, not per IP. A testing center
        // runs several phones through one NAT, so an IP-keyed limit would lock
        // out legitimate scanning long before it inconvenienced anyone walking
        // the sequential PROCTAD ID range from a leaked link.
        RateLimiter::for('scanner-link', fn (Request $request) => Limit::perMinute(240)
            ->by((string) $request->route('token')));

        $this->applyEmailKillSwitch();
    }

    /**
     * Global outbound-email kill switch (Settings → General).
     *
     * Redirecting the default mailer to 'log' catches *every* sender —
     * NotificationMailer, CertificateService's direct Mail:: calls, password
     * resets, queued notifications, console commands — instead of relying on
     * each call site to remember to check the flag. Messages still render and
     * land in the log, so nothing breaks; they just don't leave the building.
     */
    private function applyEmailKillSwitch(): void
    {
        try {
            if (! Setting::emailSendingEnabled()) {
                config(['mail.default' => 'log']);
            }
        } catch (Throwable) {
            // Settings table not migrated yet (fresh install, `migrate` itself):
            // leave mail configured as-is rather than blocking the boot.
        }
    }
}
