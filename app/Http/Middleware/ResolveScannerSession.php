<?php

namespace App\Http\Middleware;

use App\Models\ScannerSession;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the /scan/{token} link into a ScannerSession and stashes it on the
 * request for ScannerController. An unknown, expired or revoked token gets the
 * same explained 403 page as an expired assignment-confirmation link — the
 * people meeting it are venue staff mid-examination, not developers.
 */
class ResolveScannerSession
{
    public const ATTRIBUTE = 'scannerSession';

    public function handle(Request $request, Closure $next): Response
    {
        $session = ScannerSession::query()
            ->where('token', (string) $request->route('token'))
            ->with(['creator', 'examinationSchool.school', 'examination', 'training'])
            ->first();

        if (! $session || ! $session->isActive() || ! $session->creator) {
            return Inertia::render('Errors/LinkExpired', [
                'title' => 'This scanner link is no longer active',
                'message' => 'Scanner links are issued for a single examination day and can be switched off at any time. This one has expired or was revoked.',
                'followUp' => 'Please ask your Field Office administrator to issue a new scanner link.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $request->attributes->set(self::ATTRIBUTE, $session);

        return $next($request);
    }
}
