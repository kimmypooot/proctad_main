<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('member.login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        // Only pre-registered accounts may sign in — there is no self-provisioning
        // through Google. Unknown emails are directed to their Testing Center.
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            return redirect()->route('member.login')
                ->with('error', 'No PROCTAD account is registered under that Google email. Please contact your Testing Center.');
        }

        if ($user->locked_until?->isFuture()) {
            return redirect()->route('member.login')
                ->with('error', 'This account is temporarily locked. Please try again later.');
        }

        if (! $user->is_active) {
            return redirect()->route('member.login')
                ->with('error', 'This account has been deactivated. Please contact your administrator.');
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

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
