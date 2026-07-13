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

        if ($user !== null && $user->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'login' => __('This account is temporarily locked. Try again after :time.', [
                    'time' => $user->locked_until->format('g:i A'),
                ]),
            ]);
        }

        if ($user !== null && ! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('This account has been deactivated. Please contact your administrator.'),
            ]);
        }

        if ($user !== null && $user->member?->blacklists()->where('status', BlacklistStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'login' => __('This account has been blacklisted. Please contact your administrator.'),
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

        RateLimiter::clear($throttleKey);

        $user = $request->user();
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
