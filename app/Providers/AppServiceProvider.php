<?php

namespace App\Providers;

use App\Models\Setting;
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
