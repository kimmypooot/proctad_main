<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Signs staff in from the IMIS portal. IMIS sends the browser here with a
 * one-time `?sso_token=`; this redeems it server-to-server at IMIS, which says
 * who it was issued for, what role they hold in PROCTAD (`role_code`, a
 * UserRole value) and which organizational unit they belong to.
 *
 * IMIS is the only place staff are managed: the account here is a stub that
 * only records who did what. It is linked by `imis_user_id`, created on first
 * sign-in, and its name, role and field office are rewritten from IMIS on
 * every sign-in. People linked to IMIS have no PROCTAD password or Google
 * sign-in. IMIS can also grant the `member` role (CSC employees who serve as
 * test administrators); external members are not in IMIS and sign in with Google.
 */
class ImisSsoController extends Controller
{
    /**
     * Where IMIS sends the browser. Shows the "signing you in" splash straight
     * away; the page then posts the token back to store() while it stays up, so
     * the person is not left on a blank tab during the round trip to IMIS.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $token = $request->query('sso_token');

        if (! config('services.imis.sso_url') || ! is_string($token) || $token === '') {
            return $this->refuse('Single sign-on is not available. Please sign in below.');
        }

        return Inertia::render('Auth/ImisSso', ['token' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $url = config('services.imis.sso_url');
        $token = $request->input('sso_token');

        if (! $url || ! is_string($token) || $token === '') {
            return $this->refuse('Single sign-on is not available. Please sign in below.');
        }

        try {
            $response = Http::asForm()->timeout(10)->post($url, [
                'token' => $token,
                'audience' => config('services.imis.audience'),
            ]);
        } catch (Throwable $e) {
            Log::warning('IMIS sign-in failed', ['message' => $e->getMessage()]);

            return $this->refuse('IMIS could not be reached. Please sign in below.');
        }

        $imis = $response->successful() && $response->json('success') === true ? $response->json('user') : null;

        if (! is_array($imis) || ! is_int($imis['id'] ?? null) || ! is_string($imis['username'] ?? null)) {
            return $this->refuse('Your IMIS sign-in could not be used here. Please sign in below.');
        }

        $role = UserRole::tryFrom((string) ($imis['role_code'] ?? ''));

        if ($role === null) {
            return $this->refuse('You have not been given a ProCTAD role in IMIS. Please contact your administrator.');
        }

        $office = $this->fieldOffice($imis);

        if ($role->isFieldOfficeScoped() && $office === null) {
            return $this->refuse('Your IMIS office does not match a ProCTAD field office. Please contact your administrator.');
        }

        $user = $this->account($imis, $role);

        if ($user === false) {
            return $this->refuse('Your account clashes with an existing ProCTAD account. Please contact your administrator.');
        }

        if ($user && (! $user->is_active || $user->locked_until?->isFuture())) {
            return $this->refuse('This account cannot sign in at the moment. Please contact your administrator.');
        }

        $user ??= new User;
        $first = trim((string) ($imis['fname'] ?? ''));
        $last = trim((string) ($imis['lname'] ?? ''));

        $user->forceFill([
            'imis_user_id' => $imis['id'],
            'name' => trim("{$first} {$last}") ?: $imis['username'],
            'first_name' => $first ?: null,
            'middle_name' => ($imis['mname'] ?? '') ?: null,
            'last_name' => $last ?: null,
            'username' => $imis['username'],
            'email' => $imis['email'],
            'role' => $role,
            // Regional roles can have no office; keep what is known if IMIS has none.
            'field_office_id' => $office?->id ?? ($role->isFieldOfficeScoped() ? null : $user->field_office_id),
            // Keeps the current photo when IMIS has none to give.
            'google_avatar' => $this->photoUrl($imis) ?? $user->google_avatar,
            'last_login_at' => now(),
        ]);

        if (! $user->exists) {
            // A stub never signs in with a password; the value is unguessable.
            $user->forceFill([
                'password' => Str::random(64),
                'must_change_password' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $user->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'field_office_id' => $user->field_office_id,
            'changes' => ['ip' => $request->ip(), 'method' => 'imis'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The staff account for this IMIS user: by its IMIS id, else an unlinked
     * account with the same username or email. Null when there is none (one is
     * created); false when the match is a member and IMIS grants a staff role,
     * which must never promote a member on a matching email, or when the
     * username/email is taken by an account linked to a different IMIS user.
     */
    private function account(array $imis, UserRole $role): User|false|null
    {
        if ($linked = User::where('imis_user_id', $imis['id'])->first()) {
            return $linked;
        }

        $matches = User::where(fn ($query) => $query->where('username', $imis['username'])->orWhere('email', $imis['email']))->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $match = $matches->first();

        return $matches->count() === 1 && $match->imis_user_id === null && ($match->role->isStaff() || ! $role->isStaff()) ? $match : false;
    }

    /**
     * The person's IMIS photo (a public URL on the IMIS host), kept in
     * `google_avatar`, which both the header and the profile already show.
     * Only an http(s) URL that fits the column; IMIS is the only writer for
     * linked accounts, so there is no Google avatar to clobber.
     */
    private function photoUrl(array $imis): ?string
    {
        $url = $imis['photo_url'] ?? null;

        return is_string($url) && strlen($url) <= 255 && preg_match('#^https?://#i', $url) ? $url : null;
    }

    /** The PROCTAD field office for the person's IMIS unit. */
    private function fieldOffice(array $imis): ?FieldOffice
    {
        return match ($imis['office_type'] ?? null) {
            'regional_support_unit' => FieldOffice::where('is_regional', true)->first(),
            'field_office', 'satellite_office' => FieldOffice::where('imis_code', $imis['office_code'] ?? '')->first(),
            default => null,
        };
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
