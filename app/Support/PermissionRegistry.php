<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves whether a role holds a permission: the built-in default, unless an
 * administrator has overridden it in role_permissions.
 *
 * The defaults below are a transcription of the role checks the policies used
 * before permissions existed, so a fresh install behaves identically and the
 * matrix opens showing what the system already does rather than a blank slate.
 */
class PermissionRegistry
{
    private const CACHE_KEY = 'role_permission_overrides';

    /**
     * Super Admin is never consulted against the table — it holds everything,
     * always. Without this an administrator could revoke UsersManage from their
     * own role and lock every account out of the permissions page, recoverable
     * only by editing the database by hand.
     */
    public static function has(UserRole $role, Permission $permission): bool
    {
        if ($role === UserRole::SuperAdmin) {
            return true;
        }

        return self::overrides()["{$role->value}|{$permission->value}"]
            ?? self::isDefault($role, $permission);
    }

    public static function isDefault(UserRole $role, Permission $permission): bool
    {
        return in_array($role, self::defaults()[$permission->value] ?? [], true);
    }

    /**
     * The table is checked for existence because authorization can run before
     * migrations have (artisan commands on a fresh clone), and a missing table
     * should mean "no overrides", not a fatal error.
     *
     * @return array<string, bool> keyed "role|permission"
     */
    private static function overrides(): array
    {
        if (! Schema::hasTable('role_permissions')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('role_permissions')
            ->get(['role', 'permission', 'granted'])
            ->mapWithKeys(fn ($row) => ["{$row->role}|{$row->permission}" => (bool) $row->granted])
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The role tiers the policies were written against.
     *
     * @return array<string, list<UserRole>>
     */
    private static function defaults(): array
    {
        $admin = [UserRole::SuperAdmin, UserRole::EsdAdmin];
        $regionWide = [...$admin, UserRole::DirectorIv, UserRole::DirectorIii];
        // The roles that may write within their own jurisdiction.
        $write = [...$admin, UserRole::FoAdmin, UserRole::FieldDirector];
        // Everyone who is not a PROCTAD member using self-service.
        $staff = [...$regionWide, UserRole::FieldDirector, UserRole::FoAdmin];

        return [
            Permission::MembersView->value => $staff,
            Permission::MembersManage->value => $write,

            Permission::ExaminationsView->value => $staff,
            Permission::ExaminationsManage->value => $admin,
            Permission::ExaminationSchoolsManage->value => $write,
            Permission::ExamAssignmentsManage->value => $write,

            Permission::TrainingsView->value => $staff,
            Permission::TrainingsManage->value => $write,
            Permission::TrainingsDelete->value => $admin,
            Permission::TrainingAssignmentsManage->value => $write,

            Permission::CertificatesView->value => $staff,
            // The union of every role that can approve something today: the
            // types' own approver roles, plus the two fallback paths in
            // CertificatePolicy::decide(). Which types a role may actually
            // decide stays with the certificate type.
            Permission::CertificatesDecide->value => [...$regionWide, UserRole::FieldDirector],
            // The fallback the two admin roles have always had: approve a type
            // even when their post is not among its designated approvers.
            Permission::CertificatesDecideAnyType->value => $admin,
            Permission::CertificatesRegenerate->value => $staff,

            Permission::OepView->value => $staff,
            Permission::OepManage->value => $write,
            Permission::OepAssignmentsManage->value => $write,

            Permission::SchoolsView->value => $staff,
            Permission::SchoolsManage->value => $write,

            Permission::TestingCentersView->value => $staff,
            Permission::TestingCentersManage->value => $write,
            Permission::TestingCentersDesignate->value => $regionWide,

            Permission::FieldOfficesView->value => $admin,
            Permission::FieldOfficesManage->value => $admin,

            Permission::SignatoriesView->value => $staff,
            Permission::SignatoriesManage->value => $write,

            Permission::ExamTypesView->value => $staff,
            Permission::ExamTypesManage->value => $admin,

            Permission::BlacklistsView->value => $write,
            Permission::BlacklistsManage->value => $write,

            Permission::EvaluationsView->value => $staff,

            Permission::ScannerSessionsCreate->value => $write,
            Permission::ScannerSessionsRevoke->value => $write,

            Permission::EmailTemplatesManage->value => $admin,
            Permission::LetterheadsManage->value => $admin,
            Permission::DesignationsManage->value => $admin,
            Permission::FeeSchedulesManage->value => $admin,
            Permission::SettingsManage->value => $admin,
            Permission::UsersManage->value => $admin,
        ];
    }
}
