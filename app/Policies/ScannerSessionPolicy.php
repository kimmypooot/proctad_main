<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ScannerSession;
use App\Models\User;

class ScannerSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ScannerSessionsCreate);
    }

    public function revoke(User $user, ScannerSession $session): bool
    {
        if (! $user->hasPermission(Permission::ScannerSessionsRevoke)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($session->field_office_id, $user->scopedFieldOfficeIds(), true);
    }
}
