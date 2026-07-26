<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class FieldOfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FieldOfficesView);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(Permission::FieldOfficesManage);
    }
}
