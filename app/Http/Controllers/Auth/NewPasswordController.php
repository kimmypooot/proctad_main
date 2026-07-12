<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Show the reset-password page (opened from the emailed link).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Set the new password.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => $request->string('password'),
                    'must_change_password' => false,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'password_reset',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'field_office_id' => $user->field_office_id,
                    'changes' => ['ip' => $request->ip()],
                ]);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('login')->with('status', __('Your password has been reset. Please sign in.'));
    }
}
