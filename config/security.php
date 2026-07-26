<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The load balancer / reverse proxy in front of the application. Until this
    | is set, X-Forwarded-Proto is ignored, which has three consequences that
    | all look like something else:
    |
    |   - $request->secure() returns false behind TLS termination, so the
    |     Secure cookie flag and HSTS are silently suppressed;
    |   - $request->ip() returns the proxy's address, so every rate limiter
    |     (login throttling included) collapses into one shared bucket;
    |   - audit_logs records the proxy for every event instead of the actor.
    |
    | Set TRUSTED_PROXIES to the proxy's address or CIDR, comma-separated.
    | Use '*' ONLY when the application is unreachable except through the
    | proxy — otherwise a client can forge X-Forwarded-For and defeat both
    | the rate limiting and the audit trail.
    |
    */

    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
    )),

    /*
    |--------------------------------------------------------------------------
    | Trusted Hosts
    |--------------------------------------------------------------------------
    |
    | Absolute URLs — including password reset links — are built from the
    | request's Host header. Without an allow-list, an attacker sends
    | `Host: attacker.example` to /forgot-password and the victim receives a
    | genuine, correctly-signed CSC email whose reset link points at the
    | attacker. Leave empty to disable host checking (local development only).
    |
    */

    'trusted_hosts' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))),
    )),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Deploy report-only first (CSP_REPORT_ONLY=true), watch one full exam
    | cycle for violations, then enforce. Enforcing an untested policy on exam
    | day breaks the scanner for venue staff who have no way to fix it.
    |
    */

    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Database Dump Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories proctad:preflight checks for stray .sql files. A legacy export
    | of this system carried staff names, csc.gov.ph addresses and password
    | hashes; a deployment that copies the working tree wholesale would put it
    | on the live host. Configurable so the check itself can be tested.
    |
    */

    'dump_scan_paths' => [
        base_path(),
        storage_path(),
    ],

    /*
    | Origins the application legitimately reaches out to. Kept here rather
    | than inline in the middleware so a font or avatar host can be added
    | without touching code.
    */
    'csp_allowed' => [
        // Vite::fonts() emits <link> tags against this host (see app.blade.php).
        // Self-hosting the fonts would let both entries below be removed.
        'style' => ['https://fonts.bunny.net'],
        'font' => ['https://fonts.bunny.net'],
        // Google profile pictures, stored on users.google_avatar by Socialite.
        'img' => ['https://lh3.googleusercontent.com'],
    ],

];
