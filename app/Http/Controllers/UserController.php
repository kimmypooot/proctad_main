<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Staff accounts and test administrators live in the same table but are not
     * the same thing to administer: staff have a role and a field office, test
     * administrators have a registry record and a testing center. A single list
     * could only show the columns they share — which is why the Field Office
     * column read "—" for every member account, and why filtering by any office
     * hid all of them. So the page is two tabs, and each queries and presents
     * only what its own tab can display.
     *
     * The tabs are two hats, not two populations: Commission staff can hold an
     * accreditation too (see App\Support\Workspace — the switcher exists exactly
     * for them), so a Field Office Staff who proctors belongs on both tabs and
     * appears on both. Membership of the administrators tab is therefore the
     * registry record, never `role`.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $tab = $this->tab($request);

        $users = User::query()
            ->with([
                'fieldOffice:id,name,code',
                // Loaded on both tabs: a staff account that also holds an
                // accreditation says so on the staff tab, and links through.
                'member:id,user_id,proctad_id,status,testing_center_id',
                'member.testingCenter:id,name',
            ])
            ->when(
                $tab === 'members',
                fn ($q) => $q->where(fn ($q) => $q
                    ->whereHas('member')
                    // Members whose account exists but whose registry record does
                    // not yet — the cleanup queue below filters down to these.
                    ->orWhere('role', UserRole::Member)),
                fn ($q) => $q->whereNot('role', UserRole::Member),
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%"));
            })
            // Role and field office describe a staff posting; testing center and
            // the registry link describe a test administrator. Each set is bound
            // to its own tab so the two can never contradict each other — asking
            // for role=fo_admin among the members returned nothing, silently.
            ->when($tab === 'staff', fn ($q) => $q
                ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
                ->when($request->filled('field_office_id'), fn ($q) => $q->where('field_office_id', $request->integer('field_office_id'))))
            ->when($tab === 'members', fn ($q) => $q
                ->when($request->filled('testing_center_id'), fn ($q) => $q->whereHas(
                    'member',
                    fn ($m) => $m->where('testing_center_id', $request->integer('testing_center_id')),
                ))
                // Registration creates the account and the registry record in one
                // transaction (RegisteredUserController::store), so an unlinked
                // member account can only come from legacy data or an account
                // predating that change. It is a cleanup queue, not intake.
                //
                // ->value() matters: string() returns a Stringable, and an object
                // is never === a string, so this condition was always false and
                // the filter silently did nothing.
                ->when($request->string('linked')->value() === 'unlinked', fn ($q) => $q->whereDoesntHave('member')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        // Hidden field offices already assigned to a user must still appear in
        // the create/edit forms so an existing assignment doesn't silently blank out.
        $referencedIds = User::whereNotNull('field_office_id')->distinct()->pluck('field_office_id');

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'tab' => $tab,
            'filters' => $request->only('search', 'role', 'field_office_id', 'testing_center_id', 'linked'),
            'roles' => $this->roleOptions(),
            // Members reach the registry by self-registering, which creates the
            // account for them; an admin-created "member" would be an account
            // with no registry record — the very state the cleanup queue exists
            // to drain. So only staff roles can be created here.
            'creatableRoles' => $this->roleOptions(staffOnly: true),
            'fieldOffices' => FieldOffice::orderBy('name')->get(['id', 'name', 'code']),
            'assignableFieldOffices' => FieldOffice::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $referencedIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'testingCenters' => TestingCenter::orderBy('name')->get(['id', 'name']),
            'counts' => [
                'staff' => User::whereNot('role', UserRole::Member)->count(),
                // Deliberately overlaps `staff`: dual-hat accounts are counted
                // under both hats because they are administered under both.
                'members' => User::where(fn ($q) => $q
                    ->whereHas('member')
                    ->orWhere('role', UserRole::Member))->count(),
                'unlinked' => User::where('role', UserRole::Member)->whereDoesntHave('member')->count(),
            ],
            'can' => [
                'create' => $request->user()->can('create', User::class),
                // Whether to offer "Register as test administrator" on a staff
                // row. Separate ability, separate page: the record is created on
                // /members, this only links across to it.
                'registerMember' => $request->user()->can('create', Member::class),
            ],
        ]);
    }

    /** @return 'staff'|'members' */
    private function tab(Request $request): string
    {
        return $request->string('tab')->value() === 'members' ? 'members' : 'staff';
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            ...$this->nameRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:64', 'unique:users,username'],
            'role' => ['required', Rule::enum(UserRole::class)->except(UserRole::Member)],
            'field_office_id' => $this->fieldOfficeRules($request),
        ]);

        $user = User::create([
            ...$validated,
            'name' => $this->displayName($validated),
            'password' => Str::password(32),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        Password::sendResetLink($user->only('email'));

        return back()->with('success', "{$user->name} added. A password setup link has been emailed to {$user->email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        // Role stays editable here, including on a member account: a test
        // administrator who joins the Commission is promoted through this form,
        // and that is the only route for it. What the *form* offers differs by
        // tab — a member's testing center and agency belong to their registry
        // record, not here — but the endpoint keeps accepting the full set.
        $validated = $request->validate([
            ...$this->nameRules(),
            'role' => ['required', Rule::enum(UserRole::class)],
            'field_office_id' => $this->fieldOfficeRules($request),
            'is_active' => ['required', 'boolean'],
        ]);

        abort_if(
            $user->id === $request->user()->id && ! $validated['is_active'],
            422,
            'You cannot deactivate your own account.',
        );

        // The display name is derived, never edited directly, so that the one
        // shown in listings can never drift from the parts it is built from.
        $user->update([...$validated, 'name' => $this->displayName($validated)]);

        return back()->with('success', "{$user->name} updated.");
    }

    /**
     * Email and username are deliberately not editable here. Both are login
     * identifiers, and the email is also the password-reset destination and the
     * key that links a member account to its PROCTAD registry record — changing
     * one in passing is how an account quietly becomes unreachable.
     *
     * @return array<string, list<string>>
     */
    private function nameRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @param  array<string, mixed>  $parts */
    private function displayName(array $parts): string
    {
        return trim(collect([
            $parts['first_name'], $parts['middle_name'] ?? null,
            $parts['last_name'], $parts['suffix'] ?? null,
        ])->filter()->implode(' '));
    }

    /**
     * The office is optional in general but required for Field Office roles:
     * their whole jurisdiction is derived from it (User::scopedTestingCenterIds
     * returns nothing without one), so an FO Admin saved with no office can see
     * no records at all. Region-wide roles get their reach from the role itself
     * and may legitimately have none.
     *
     * @return list<mixed>
     */
    private function fieldOfficeRules(Request $request): array
    {
        return [
            Rule::requiredIf(
                fn () => UserRole::tryFrom((string) $request->input('role'))?->isFieldOfficeScoped() ?? false
            ),
            'nullable',
            'exists:field_offices,id',
        ];
    }

    /**
     * Admin-initiated password reset: emails the same signed reset link the
     * user could request themselves, without ever exposing a generated
     * password to the admin.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        Password::sendResetLink($user->only('email'));

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password_reset_sent',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'field_office_id' => $user->field_office_id,
            'changes' => ['sent_by' => $request->user()->id],
        ]);

        return back()->with('success', "Password reset link sent to {$user->email}.");
    }

    private function roleOptions(bool $staffOnly = false): array
    {
        return collect(UserRole::cases())
            ->when($staffOnly, fn ($roles) => $roles->filter(fn (UserRole $role) => $role->isStaff()))
            ->map(fn ($role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->all();
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'field_office' => $user->fieldOffice?->only('id', 'name', 'code'),
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $user->last_login_at?->format('M d, Y H:i'),
            // Only meaningful for role === member — whether a PROCTAD registry
            // record has been linked yet (see MemberController::resolveAccount()).
            'has_member_record' => $user->role === UserRole::Member ? $user->member !== null : null,
            // The registry side of the account, present on either tab because
            // staff can hold an accreditation as well. Deliberately shallow:
            // this page answers "can they sign in, and who are they", while the
            // member record itself — eligibility, assignments, history — stays
            // on /members.
            'member' => $user->member ? [
                'id' => $user->member->id,
                'proctad_id' => $user->member->proctad_id,
                'status' => $user->member->status->value,
                'status_label' => $user->member->status->label(),
                'testing_center' => $user->member->testingCenter?->only('id', 'name'),
            ] : null,
        ];
    }
}
