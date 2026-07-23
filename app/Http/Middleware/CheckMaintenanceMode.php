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
 * Commission staff (Super Admin, ESD Admin, the regional directors, Field Director,
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
        //
        // 'member.login' is deliberately NOT here: the portal is closed to
        // members, so offering them a sign-in form only lands them on this
        // notice with nothing they can do. They see the notice instead.
        //
        // The Google routes stay open because staff sign in that way too (see
        // the button on Auth/Login.vue), not only members. A member who goes
        // through Google directly can still authenticate and will then meet the
        // notice — with a sign-out control on it.
        'login', 'login.store', 'logout',
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
        //
        // `authenticated` is passed explicitly rather than read from Inertia's
        // shared props: this middleware runs before HandleInertiaRequests (see
        // bootstrap/app.php) precisely so a closed site doesn't assemble props
        // it will never use, which means `auth.user` is not available here.
        // Without this flag a signed-in member is stranded — every route shows
        // this notice, and the page has no way to know it should offer a way
        // out. POST /logout is exempt and works; nothing could reach it.
        return Inertia::render('Maintenance', [
            'authenticated' => $request->user() !== null,
        ])
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
