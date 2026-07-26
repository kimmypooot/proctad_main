<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->enforceTransportSecurity();
        $this->definePasswordPolicy();

        // Public scanner links: budget per link, not per IP. A venue
        // runs several phones through one NAT, so an IP-keyed limit would lock
        // out legitimate scanning long before it inconvenienced anyone walking
        // the sequential PROCTAD ID range from a leaked link.
        RateLimiter::for('scanner-link', fn (Request $request) => Limit::perMinute(240)
            ->by((string) $request->route('token')));

        // The public evaluation lookup, for the same reason the /verify routes
        // are throttled: assignment ids are sequential, and each lookup returns
        // a respondent's name plus the whole venue roster. A respondent finds
        // themselves once; nobody legitimately does this hundreds of times.
        RateLimiter::for('evaluation-lookup', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));

        // Report exports hydrate the full member or service-record set into
        // memory in-process. A handful of concurrent requests is enough to
        // exhaust the PHP-FPM pool, so cap them well below what a person clicks.
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->id ?: $request->ip()));

        $this->applyEmailKillSwitch();
    }

    /**
     * Trusted proxies and HTTPS.
     *
     * Set here rather than in bootstrap/app.php because that file's middleware
     * callback runs on afterResolving(HttpKernel) — before the configuration is
     * loaded — and TrustProxies::at() takes only an array or string, with no
     * closure form to defer the lookup. boot() runs after configuration and
     * before any request reaches the middleware, so the static lands in time.
     */
    private function enforceTransportSecurity(): void
    {
        if ($proxies = config('security.trusted_proxies')) {
            TrustProxies::at($proxies);
            TrustProxies::withHeaders(
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        // Belt and braces with the web server's own redirect: a generated URL
        // that starts http:// is a downgrade the browser follows before any
        // redirect can intervene, and emailed links live for days.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Staff accounts reach the regional member registry — names, birth dates,
     * contact details, all PII under RA 10173 — with a password as the only
     * factor, since there is no MFA. Eight characters of letters and digits
     * admits "password1", so the floor sits at twelve with a breach check.
     *
     * uncompromised() calls the Have I Been Pwned range API (k-anonymity: only
     * a five-character hash prefix leaves the server). It is skipped outside
     * production so tests and local development do not depend on the network.
     */
    private function definePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->numbers()->mixedCase();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : Password::min(8)->letters()->numbers();
        });
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
