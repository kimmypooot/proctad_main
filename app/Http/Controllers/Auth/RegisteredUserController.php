<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EligibilityRequirement;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page. Registration is Google-only: until the
     * visitor has connected via Google (see GoogleAuthController::callback(),
     * which stashes the pending identity in the session), the page shows
     * just the "Continue with Google" prompt. Once `google` is present, the
     * frontend reveals the details form, prefilled from the Google identity.
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
            // Registrants pick the testing center (a city) they serve, not a
            // field office. The office is an internal, administrative fact —
            // Tacloban City is served by Leyte I and Leyte II in turn, so an
            // applicant has no way to know which of the two to choose. The
            // server resolves it from the center (see store()).
            'testingCenters' => TestingCenter::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Handle an incoming registration request. Registration always begins
     * with Google sign-in (see GoogleAuthController) — the identity is
     * trusted from the session set there, never from the request body.
     * Creates the login account AND the PROCTAD Member record together,
     * immediately: there is no separate staff-approval step, so everything
     * the Member record needs is collected right here.
     */
    public function store(Request $request): RedirectResponse
    {
        $googlePending = $request->session()->get('google_pending_registration');

        abort_unless($googlePending, 403, 'Please connect your Google account before registering.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'mobile_number' => ['required', 'string', 'regex:/^(\+639|09)\d{9}$/'],
            'agency' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'testing_center_id' => [
                'required',
                Rule::exists('testing_centers', 'id')->where('is_active', true),
            ],
            'terms' => ['accepted'],
        ], [
            'mobile_number.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).',
            'date_of_birth.before' => 'You must be at least 18 years old to register.',
            'testing_center_id.required' => 'Please select your Testing Center.',
            'testing_center_id.exists' => 'That Testing Center is not accepting registrations.',
            'terms.accepted' => 'You must accept the Terms and Conditions to register.',
        ]);

        // The applicant chose a city; the administrative office follows from it.
        // A center with no handling office cannot take registrations — that is a
        // configuration gap, not something the applicant can fix, so fail loudly.
        $testingCenter = TestingCenter::findOrFail($validated['testing_center_id']);
        $fieldOfficeId = $testingCenter->primaryFieldOfficeId();

        if ($fieldOfficeId === null) {
            throw ValidationException::withMessages([
                'testing_center_id' => 'That Testing Center has no Field Office assigned yet. '
                    .'Please contact CSC Regional Office VIII.',
            ]);
        }

        $email = $googlePending['email'];

        // The email was already unique when the Google identity was captured,
        // but re-check here in case another registration completed meanwhile.
        abort_if(
            User::where('email', $email)->exists(),
            422,
            'An account with this email already exists. Please sign in instead.',
        );

        // A matching PROCTAD Member record may already exist (e.g. a Testing
        // Center registered this person directly) even though no login account
        // was linked to it yet. `members.email` is DB-unique, so a plain email
        // collision must always be caught here first — check it explicitly for
        // a clean validation error instead of a raw SQL failure.
        if (Member::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A PROCTAD record already exists with this email address. '
                    .'Please sign in instead, or contact your Field Office if you believe this is an error.',
            ]);
        }

        // Then the "same person registering under a different email" case —
        // increasingly likely now that members are advised to move off agency
        // Google accounts, which changes their email but not who they are.
        //
        // Name + date of birth, not mobile number: dual-SIM is the norm here, so
        // the same person routinely holds two numbers and would slip past a
        // phone match. The old check also compared first and last name without
        // suffix, so a "Jr." sharing a household number with his father was
        // falsely blocked — a birth date separates them.
        //
        // Names are stored uppercase (see the Member name mutators); TRIM as
        // well so this agrees with DuplicateMembersController's normalisation,
        // or prevention and detection disagree about what "same name" means.
        // withTrashed: PROCTAD IDs are permanently reserved, so a removed
        // member re-registering is still a duplicate.
        //
        // The birth date is compared in PHP, not SQL: `date_of_birth` is an
        // encrypted cast (PII, RA 10173) and Laravel encrypts with a random IV,
        // so the ciphertext differs on every write and no WHERE clause against
        // it can ever match. Narrowing by name first keeps the decrypted set to
        // the handful of people who share that name.
        $birthDate = Carbon::parse($validated['date_of_birth'])->toDateString();

        $duplicate = Member::withTrashed()
            ->whereRaw('UPPER(TRIM(first_name)) = ?', [mb_strtoupper(trim($validated['first_name']))])
            ->whereRaw('UPPER(TRIM(last_name)) = ?', [mb_strtoupper(trim($validated['last_name']))])
            ->get()
            ->contains(fn (Member $existing) => $existing->date_of_birth === $birthDate);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'email' => 'A PROCTAD record already exists for this name and date of birth. '
                    .'If that is you, please sign in instead — you can use the password option if you '
                    .'can no longer access your Google account. Otherwise contact your Field Office.',
            ]);
        }

        $member = DB::transaction(function () use ($validated, $googlePending, $email, $fieldOfficeId) {
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
                'field_office_id' => $fieldOfficeId,
                // Google-registered accounts sign in via Google only — this random
                // value just satisfies the required, non-nullable column.
                'password' => Str::random(40),
            ]);

            $user->forceFill([
                'google_id' => $googlePending['google_id'],
                'google_avatar' => $googlePending['avatar'],
                'email_verified_at' => now(),
            ])->save();

            $memberData = [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'sex' => $validated['sex'],
                'date_of_birth' => $validated['date_of_birth'],
                'email' => $email,
                'mobile_number' => $validated['mobile_number'],
                'agency' => $validated['agency'],
                'position' => $validated['position'] ?? null,
                'field_office_id' => $fieldOfficeId,
                'testing_center_id' => $validated['testing_center_id'],
                'user_id' => $user->id,
            ];

            $member = Member::create($memberData);

            foreach (EligibilityRequirement::cases() as $requirement) {
                $member->requirements()->create(['requirement' => $requirement]);
            }

            return $member;
        });

        $request->session()->forget('google_pending_registration');

        Auth::login($member->user);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to PROCTAD! Your PROCTAD ID is {$member->proctad_id}.");
    }
}
