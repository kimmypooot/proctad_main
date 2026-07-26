<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom names for the built-in roles.
 *
 * Presentation only, and deliberately so: the role a user holds is still the
 * enum value in users.role, which is what every policy and permission matches
 * on. Renaming changes what the label says, never what the role can do — so
 * this page cannot break access the way a real role editor could.
 */
class RoleLabelRegistry
{
    private const CACHE_KEY = 'role_labels';

    public static function get(UserRole $role): string
    {
        return self::overrides()[$role->value] ?? $role->defaultLabel();
    }

    /** @return array<string, string> every role's current label, keyed by value */
    public static function all(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => self::get($role)])
            ->all();
    }

    public static function set(UserRole $role, string $label): void
    {
        DB::table('role_labels')->updateOrInsert(
            ['role' => $role->value],
            ['label' => $label, 'updated_at' => now(), 'created_at' => now()],
        );

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Guarded against a missing table for the same reason as
     * PermissionRegistry: labels are read during console commands that may run
     * before migrations on a fresh clone.
     *
     * @return array<string, string>
     */
    private static function overrides(): array
    {
        if (! Schema::hasTable('role_labels')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('role_labels')
            ->pluck('label', 'role')
            ->all());
    }
}
