<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ScannerSession;
use App\Models\User;

/**
 * Issuing a scanner link hands out the ability to confirm attendance without
 * logging in, so it follows the same authority as running the scanner itself
 * (routes/web.php) — and FO-scoped roles may only issue links that stay inside
 * their own Testing Center.
 */
class ScannerSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function revoke(User $user, ScannerSession $session): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $session->field_office_id;
    }
}
