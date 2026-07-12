<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the change-password page. `forced` is true when the user was
     * redirected here by the must-change-password middleware.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'forced' => $request->user()->must_change_password,
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password'),
            'must_change_password' => false,
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'field_office_id' => $user->field_office_id,
            'changes' => ['ip' => $request->ip()],
        ]);

        return redirect()->route('dashboard')->with('status', __('Your password has been updated.'));
    }
}
