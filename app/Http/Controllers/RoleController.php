<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RoleLabelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renaming the built-in roles.
 *
 * Restricted to Super Administrator by role rather than by a permission, so it
 * cannot be granted away in the permission matrix — unlike the neighbouring
 * Role Permissions page, which ESD Admin can also reach.
 *
 * Only labels are editable. Roles themselves stay in code: several are named
 * directly by the rules that route certificate approvals and compose the
 * Regional Examination Committee (CertificateType::approverRoles,
 * ExamRole::reservedForRole), and each has a hand-built sidebar, so adding or
 * deleting one is a code change, not a data change.
 */
class RoleController extends Controller
{
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::SuperAdmin), 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        $counts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return Inertia::render('Settings/Roles/Index', [
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'user_count' => (int) ($counts[$role->value] ?? 0),
                    'reach' => $this->reach($role),
                ])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'label' => ['required', 'string', 'max:100'],
        ]);

        $role = UserRole::from($validated['role']);
        $previous = $role->label();

        RoleLabelRegistry::set($role, trim($validated['label']));

        $this->record($request, 'role_renamed', [
            'role' => $role->value,
            'from' => $previous,
            'to' => $role->label(),
        ]);

        return back()->with('success', "Role renamed to \"{$role->label()}\".");
    }

    /** A plain-language note on what the role reaches, so a rename is informed. */
    private function reach(UserRole $role): string
    {
        return match (true) {
            $role->isRegionWide() => 'Region-wide',
            $role->isFieldOfficeScoped() => 'Own field office only',
            default => 'Own records only',
        };
    }

    /** @param  array<string, mixed>  $changes */
    private function record(Request $request, string $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $request->user()->id,
            'field_office_id' => $request->user()->field_office_id,
            'changes' => $changes,
        ]);
    }
}
