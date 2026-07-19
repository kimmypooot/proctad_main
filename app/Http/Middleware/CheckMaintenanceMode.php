<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the site to the public and to PROCTAD members when
 * Settings → Website → Maintenance mode is on.
 *
 * Commission staff (Super Admin, ESD Admin, Management, Field Director,
 * Testing Center Staff) are unaffected and keep working normally. A handful
 * of routes stay open to everyone regardless — see EXEMPT_ROUTES.
 */
class CheckMaintenanceMode
{
    /**
     * Public routes that must keep working even during maintenance. These are
     * operational rather than promotional: closing them would strand people
     * mid-task, on exam day, with no way through.
     */
    private const EXEMPT_ROUTES = [
        // Staff need to get in to turn maintenance back off.
        'login', 'login.store', 'member.login', 'logout',
        'google.redirect', 'google.callback', 'google.cancel',
        'password.request', 'password.email', 'password.reset', 'password.store',

        // Members responding to an emailed assignment link — the link expires
        // in 7 days and can't be re-sent by the member.
        'assignments.confirm', 'assignments.confirm.store',

        // QR verification, used at venue entrances to check IDs and
        // certificates while an examination is actually running.
        'verify', 'verify-certificate',

        // Post-examination evaluation: a live form respondents fill in on or
        // just after exam day.
        'evaluations.create', 'evaluations.search', 'evaluations.resolve', 'evaluations.store',

        // The notice itself, or it would loop.
        'maintenance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isClosed($request)) {
            return $next($request);
        }

        // 503, not a redirect: tells search engines "temporarily unavailable,
        // come back" rather than letting them index the notice as the homepage.
        return Inertia::render('Maintenance')
            ->toResponse($request)
            ->setStatusCode(503);
    }

    private function isClosed(Request $request): bool
    {
        // Staff work through maintenance; members and the public do not. Signing
        // in is exempt below, so a member can still reach the login page — they
        // just meet the notice on the other side of it.
        // Null-safe on `role` as well as on the user: a record without a role
        // is not treated as staff, so a broken account can't slip past the gate.
        if ($request->user()?->role?->isStaff()) {
            return false;
        }

        if (in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
            return false;
        }

        return (bool) Setting::get('site_maintenance_mode', false);
    }
}
