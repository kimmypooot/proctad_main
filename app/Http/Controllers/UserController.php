<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
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
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::with(['fieldOffice:id,name,code', 'member:id,user_id'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%"));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('field_office_id'), fn ($q) => $q->where('field_office_id', $request->integer('field_office_id')))
            // Self-registered member accounts (via /register, including Google
            // sign-up) never get linked to a PROCTAD registry record automatically
            // — this surfaces the ones still waiting for staff to register them
            // as a Member (which auto-links by matching email).
            ->when($request->string('linked') === 'unlinked', fn ($q) => $q
                ->where('role', UserRole::Member)
                ->whereDoesntHave('member'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        // Hidden field offices already assigned to a user must still appear in
        // the create/edit forms so an existing assignment doesn't silently blank out.
        $referencedIds = User::whereNotNull('field_office_id')->distinct()->pluck('field_office_id');

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'filters' => $request->only('search', 'role', 'field_office_id', 'linked'),
            'roles' => $this->roleOptions(),
            'fieldOffices' => FieldOffice::orderBy('name')->get(['id', 'name', 'code']),
            'assignableFieldOffices' => FieldOffice::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $referencedIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'unlinkedMemberCount' => User::where('role', UserRole::Member)->whereDoesntHave('member')->count(),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:64', 'unique:users,username'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'field_office_id' => ['nullable', 'exists:field_offices,id'],
        ]);

        $user = User::create([
            ...$validated,
            'name' => trim(collect([
                $validated['first_name'], $validated['middle_name'] ?? null,
                $validated['last_name'], $validated['suffix'] ?? null,
            ])->filter()->implode(' ')),
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

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'field_office_id' => ['nullable', 'exists:field_offices,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        abort_if(
            $user->id === $request->user()->id && ! $validated['is_active'],
            422,
            'You cannot deactivate your own account.',
        );

        $user->update($validated);

        return back()->with('success', "{$user->name} updated.");
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

    private function roleOptions(): array
    {
        return collect(UserRole::cases())
            ->map(fn ($role) => ['value' => $role->value, 'label' => $role->label()])
            ->all();
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
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
        ];
    }
}
