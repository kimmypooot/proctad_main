<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects users flagged `must_change_password` to the change-password page
 * until they set a new one (replaces the legacy change_pw.php gate).
 */
class EnsurePasswordIsChanged
{
    private const ALLOWED_ROUTES = ['password.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->must_change_password
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
