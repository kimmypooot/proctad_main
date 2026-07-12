<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin);
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user);
    }
}
