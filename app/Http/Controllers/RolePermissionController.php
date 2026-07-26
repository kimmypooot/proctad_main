<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The role/permission matrix.
 *
 * Gated on managing users rather than on a permission of its own: deciding what
 * a role may do is the same authority as deciding who holds that role, and a
 * separate toggle would only create another way to lock everyone out.
 */
class RolePermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('update', $request->user());

        return Inertia::render('Settings/RolePermissions/Index', [
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    // Super Admin holds everything unconditionally, so its
                    // column is shown ticked and disabled rather than hidden —
                    // an empty column would read as "has no access".
                    'locked' => $role === UserRole::SuperAdmin,
                ])
                ->all(),
            'groups' => collect(Permission::cases())
                ->groupBy(fn (Permission $permission) => $permission->group())
                ->map(fn ($permissions, $group) => [
                    'name' => $group,
                    'permissions' => $permissions->map(fn (Permission $permission) => [
                        'value' => $permission->value,
                        'label' => $permission->label(),
                        'scope_note' => $permission->scopeNote(),
                        'roles' => collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role) => [
                                $role->value => [
                                    'granted' => PermissionRegistry::has($role, $permission),
                                    'is_default' => PermissionRegistry::isDefault($role, $permission),
                                ],
                            ])
                            ->all(),
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Toggling one cell at a time, rather than saving the whole grid: the matrix
     * is large enough that a full-form submit would make it easy to overwrite a
     * colleague's change without noticing.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('update', $request->user());

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'permission' => ['required', Rule::enum(Permission::class)],
            'granted' => ['required', 'boolean'],
        ]);

        $role = UserRole::from($validated['role']);

        abort_if(
            $role === UserRole::SuperAdmin,
            422,
            UserRole::SuperAdmin->label().' permissions cannot be changed — the role exists so there is always a way back in.',
        );

        DB::table('role_permissions')->updateOrInsert(
            ['role' => $role->value, 'permission' => $validated['permission']],
            ['granted' => $validated['granted'], 'updated_at' => now(), 'created_at' => now()],
        );

        PermissionRegistry::flush();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $validated['granted'] ? 'permission_granted' : 'permission_revoked',
            'auditable_type' => User::class,
            'auditable_id' => $request->user()->id,
            'field_office_id' => $request->user()->field_office_id,
            'changes' => ['role' => $role->value, 'permission' => $validated['permission']],
        ]);

        return back();
    }

    /** Drop every override for one role, returning it to the built-in defaults. */
    public function reset(Request $request): RedirectResponse
    {
        Gate::authorize('update', $request->user());

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        DB::table('role_permissions')->where('role', $validated['role'])->delete();

        PermissionRegistry::flush();

        $role = UserRole::from($validated['role']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'permissions_reset',
            'auditable_type' => User::class,
            'auditable_id' => $request->user()->id,
            'field_office_id' => $request->user()->field_office_id,
            'changes' => ['role' => $role->value],
        ]);

        return back()->with('success', "{$role->label()} permissions reset to their defaults.");
    }
}
