<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BlacklistStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * "Not you?" on the registration completion form — abandons the pending
     * Google identity so a stale one doesn't resurface if the visitor lands
     * back on /register without reconnecting.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('google_pending_registration');

        return redirect()->route('login');
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['message' => $e->getMessage(), 'exception' => $e::class]);

            return redirect()->route('member.login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        // Google's own verification is the only thing that makes an email
        // address safe to treat as an identity. Without this check, anyone able
        // to provision a mailbox on any domain they control — a Workspace
        // tenant costs very little — could claim the ProCTAD account holding
        // that address, because the match below is on the address alone.
        if (($googleUser->user['email_verified'] ?? false) !== true) {
            Log::warning('Google sign-in rejected: unverified email', [
                'google_id' => $googleUser->getId(),
            ]);

            return redirect()->route('member.login')
                ->with('error', 'Your Google account email address is not verified. Please verify it with Google, then try again.');
        }

        // Provider identifier first; the email address only as a fallback, and
        // only for an account that has never been linked. Previously a single
        // orWhere meant an account already bound to one Google identity would
        // still match on email and be signed into by a second one.
        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())
                ->whereNull('google_id')
                ->first();

        // No matching account: hand off to the registration form to finish
        // creating one. The Google identity (already verified by Google) is
        // kept server-side in the session — the completion form only ever
        // collects the fields Google doesn't provide (mobile number, terms),
        // never a client-supplied email/identity.
        if (! $user) {
            $request->session()->put('google_pending_registration', [
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'first_name' => $googleUser->user['given_name'] ?? null,
                'last_name' => $googleUser->user['family_name'] ?? null,
                'avatar' => $googleUser->getAvatar(),
            ]);

            return redirect()->route('register');
        }

        if ($user->locked_until?->isFuture()) {
            return redirect()->route('member.login')
                ->with('error', 'This account is temporarily locked. Please try again later.');
        }

        if (! $user->is_active) {
            return redirect()->route('member.login')
                ->with('error', 'This account has been deactivated. Please contact your administrator.');
        }

        if ($user->member?->blacklists()->where('status', BlacklistStatus::Active)->exists()) {
            return redirect()->route('member.login')
                ->with('error', 'This account has been blacklisted. Please contact your administrator.');
        }

        $user->forceFill([
            'google_id' => $user->google_id ?? $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar() ?: $user->google_avatar,
            'last_login_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'field_office_id' => $user->field_office_id,
            'changes' => ['ip' => $request->ip(), 'method' => 'google'],
        ]);

        // No unconditional "remember me". The password path makes it the user's
        // choice ($request->boolean('remember')); issuing a long-lived cookie
        // to everyone arriving through Google widened the window on any stolen
        // session for no reason the user asked for.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
