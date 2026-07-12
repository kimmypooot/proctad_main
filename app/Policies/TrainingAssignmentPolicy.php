<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TrainingAssignment;
use App\Models\User;

class TrainingAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin);
    }

    public function update(User $user, TrainingAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function delete(User $user, TrainingAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    private function manage(User $user, TrainingAssignment $assignment): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role === UserRole::FoAdmin
            && $user->field_office_id === $assignment->field_office_id;
    }
}
