<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, Training $training): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin);
    }

    public function update(User $user, Training $training): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->hasRole(UserRole::FoAdmin)
            && $training->field_office_id === $user->field_office_id;
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin);
    }

    public function complete(User $user, Training $training): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->hasRole(UserRole::FoAdmin)
            && $training->field_office_id === $user->field_office_id;
    }
}
