<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveScannerSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // Before Inertia's share, so a closed site doesn't do the work of
            // assembling shared props it will never render.
            CheckMaintenanceMode::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'scanner.session' => ResolveScannerSession::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Signed links (assignment confirmations) expire after 7 days, and the
        // people clicking them are members working from an emailed link — the
        // stock 403 is a dead end for them. Explain it and point somewhere.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return Inertia::render('Errors/LinkExpired')
                ->toResponse($request)
                ->setStatusCode(403);
        });

        // Everything else fell through to Laravel's stock error page: unstyled,
        // unbranded, no way back. Members meet these on a public government
        // site, so give them the same shell as the rest of the app.
        //
        // Skipped when debug is on, so developers keep the stack trace, and for
        // JSON callers, who want the status and not a page.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            if (config('app.debug') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // 419 is a stale CSRF token — usually a form left open too long.
            // Sending them back to the form with a message beats an error page,
            // because the page they wanted still exists and still works.
            if ($status === 419) {
                return back()->with('error', 'Your session timed out while that page was open. Please try again.');
            }

            // 503 is maintenance, already rendered by CheckMaintenanceMode.
            if (! in_array($status, [403, 404, 500], true)) {
                return $response;
            }

            // 404 reuses the existing NotFound page. The fallback route in
            // routes/web.php already renders it for unmatched URLs, but that is
            // a route — an explicit abort(404) or a failed model binding throws
            // instead, and used to land on the stock page. Same destination
            // either way rather than two competing 404 designs.
            return Inertia::render(
                $status === 404 ? 'NotFound' : 'Errors/Error',
                $status === 404 ? [] : ['status' => $status],
            )
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
