<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }
}
