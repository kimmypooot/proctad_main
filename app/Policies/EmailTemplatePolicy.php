<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::EmailTemplatesManage);
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user);
    }
}
