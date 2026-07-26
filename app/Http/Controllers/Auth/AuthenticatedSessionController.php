<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BlacklistStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /** Consecutive failures before the account is temporarily locked. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /** Minutes an account stays locked after too many failures. */
    private const LOCKOUT_MINUTES = 15;

    /**
     * Show the login page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Accepts a username or an email address in the `login` field, throttles
     * attempts per identifier+IP, and enforces a persistent account lockout
     * after repeated failures.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = mb_strtolower($request->string('login')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $field = filter_var($request->string('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $request->string('login'))->first();

        // A locked account must be refused before the password is checked —
        // otherwise the lockout does nothing, since the attacker is guessing
        // passwords, not waiting to be told. Deliberately vague about when it
        // lifts: the exact minute is a timing oracle, and a member who needs it
        // can ask their Field Office.
        if ($user !== null && $user->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'login' => __('This account is temporarily locked. Please try again later or contact your Field Office.'),
            ]);
        }

        $credentials = [$field => $request->string('login'), 'password' => $request->string('password')];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            $this->recordFailure($user);

            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        // Deactivation and blacklisting are checked only now, after the
        // password has been verified. Both used to answer before it, which
        // meant anyone could confirm an address had an account — and, worse,
        // learn that a named member had been blacklisted, an adverse
        // administrative fact, without any credential at all.
        //
        // One message covers both: whoever reaches this point already holds the
        // password, so the distinction tells an attacker something and the
        // legitimate user nothing they can act on alone.
        $user = $request->user();

        if (! $user->is_active
            || $user->member?->blacklists()->where('status', BlacklistStatus::Active)->exists()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login' => __('This account cannot sign in at the moment. Please contact your administrator.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->saveQuietly();

        $this->audit($user, 'login');

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            $this->audit($user, 'logout');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function recordFailure(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $locked = $attempts >= self::MAX_FAILED_ATTEMPTS;

        $user->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked ? now()->addMinutes(self::LOCKOUT_MINUTES) : null,
        ])->saveQuietly();

        $this->audit($user, $locked ? 'account_locked' : 'login_failed');
    }

    private function audit(User $user, string $action): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'field_office_id' => $user->field_office_id,
            'changes' => ['ip' => request()->ip()],
        ]);
    }
}
