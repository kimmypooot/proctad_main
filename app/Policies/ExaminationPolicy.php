<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\User;

class ExaminationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, Examination $examination): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin);
    }

    public function update(User $user, Examination $examination): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Examination $examination): bool
    {
        return $this->create($user);
    }
}
