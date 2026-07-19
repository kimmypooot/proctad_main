<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Inertia\Inertia;

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
    })->create();
