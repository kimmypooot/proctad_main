<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class ExamTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin);
    }
}
