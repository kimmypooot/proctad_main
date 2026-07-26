<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class ExamTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ExamTypesView);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(Permission::ExamTypesManage);
    }
}
