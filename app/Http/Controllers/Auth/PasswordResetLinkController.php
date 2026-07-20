<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the forgot-password page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            // Members reach this page from their own sign-in screen, which is
            // separate from staff sign-in — returning them to /login would strand
            // them on a username-and-password form they have no credentials for.
            'fromMember' => $request->query('from') === 'member',
        ]);
    }

    /**
     * Email a password reset link.
     *
     * Always responds with the same message so the form cannot be used to
     * probe which email addresses have accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('If an account exists for that email, a reset link has been sent.'));
    }
}
