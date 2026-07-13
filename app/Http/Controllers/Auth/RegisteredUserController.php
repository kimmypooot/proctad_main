<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EligibilityRequirement;
use App\Http\Controllers\Controller;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page. If arriving from a Google sign-in with no
     * matching account (see GoogleAuthController::callback()), the pending
     * Google identity is surfaced so the form can prefill/lock the email and
     * skip the password fields — Google already supplies the credential.
     */
    public function create(Request $request): Response
    {
        $googlePending = $request->session()->get('google_pending_registration');

        return Inertia::render('Auth/Register', [
            'google' => $googlePending ? [
                'email' => $googlePending['email'],
                'first_name' => $googlePending['first_name'],
                'last_name' => $googlePending['last_name'],
                'avatar' => $googlePending['avatar'],
            ] : null,
            'fieldOffices' => FieldOffice::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Handle an incoming registration request — either the standard
     * email/password form, or completing a Google-initiated signup (identity
     * trusted from the session set by GoogleAuthController, never from the
     * request body). Creates the login account AND the PROCTAD Member record
     * together, immediately: there is no separate staff-approval step, so
     * everything the Member record needs is collected right here.
     */
    public function store(Request $request): RedirectResponse
    {
        $googlePending = $request->session()->get('google_pending_registration');

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'mobile_number' => ['required', 'string', 'regex:/^(\+639|09)\d{9}$/'],
            'agency' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'field_office_id' => ['required', 'exists:field_offices,id'],
            'terms' => ['accepted'],
        ];

        if (! $googlePending) {
            $rules['email'] = ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'];
            $rules['password'] = ['required', 'confirmed', Password::min(8)->letters()->numbers()];
        }

        $validated = $request->validate($rules, [
            'mobile_number.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).',
            'field_office_id.required' => 'Please select your Testing Center.',
            'terms.accepted' => 'You must accept the Terms and Conditions to register.',
        ]);

        $email = $googlePending['email'] ?? $validated['email'];

        if ($googlePending) {
            // The email was already unique when the Google identity was captured,
            // but re-check here in case another registration completed meanwhile.
            abort_if(
                User::where('email', $email)->exists(),
                422,
                'An account with this email already exists. Please sign in instead.',
            );
        }

        // A matching PROCTAD Member record may already exist (e.g. a Testing
        // Center registered this person directly) even though no login account
        // was linked to it yet. `members.email` is DB-unique, so a plain email
        // collision must always be caught here first — check it explicitly for
        // a clean validation error instead of a raw SQL failure. Beyond that,
        // also catch the "same person registering under a different email"
        // case: block when mobile number AND full name both match, even if the
        // email doesn't — any ONE field alone (e.g. name only) isn't reliable
        // enough on its own to be worth blocking.
        if (Member::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A PROCTAD record already exists with this email address. '
                    .'Please sign in instead, or contact your Testing Center if you believe this is an error.',
            ]);
        }

        $duplicate = Member::where('mobile_number', $validated['mobile_number'])
            ->where('first_name', mb_strtoupper($validated['first_name']))
            ->where('last_name', mb_strtoupper($validated['last_name']))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'email' => 'A PROCTAD record already exists with this mobile number and name. '
                    .'Please sign in instead, or contact your Testing Center if you believe this is an error.',
            ]);
        }

        $member = DB::transaction(function () use ($validated, $googlePending, $email) {
            $user = User::create([
                'name' => trim(collect([
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                    $validated['suffix'] ?? null,
                ])->filter()->implode(' ')),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'email' => $email,
                'mobile_number' => $validated['mobile_number'],
                'field_office_id' => $validated['field_office_id'],
                // Google-registered accounts sign in via Google only — this random
                // value just satisfies the required, non-nullable column.
                'password' => $googlePending ? Str::random(40) : $validated['password'],
            ]);

            if ($googlePending) {
                $user->forceFill([
                    'google_id' => $googlePending['google_id'],
                    'google_avatar' => $googlePending['avatar'],
                    'email_verified_at' => now(),
                ])->save();
            }

            $memberData = [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'sex' => $validated['sex'],
                'email' => $email,
                'mobile_number' => $validated['mobile_number'],
                'agency' => $validated['agency'],
                'position' => $validated['position'] ?? null,
                'field_office_id' => $validated['field_office_id'],
                'user_id' => $user->id,
            ];

            $member = Member::create($memberData);

            foreach (EligibilityRequirement::cases() as $requirement) {
                $member->requirements()->create(['requirement' => $requirement]);
            }

            return $member;
        });

        if ($googlePending) {
            $request->session()->forget('google_pending_registration');
        }

        Auth::login($member->user);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to PROCTAD! Your PROCTAD ID is {$member->proctad_id}.");
    }
}
