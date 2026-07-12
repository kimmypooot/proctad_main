<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\NepAssignment;
use App\Models\User;

class NepAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin);
    }

    public function delete(User $user, NepAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function update(User $user, NepAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    private function manage(User $user, NepAssignment $assignment): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        $assignment->loadMissing('personnel');

        return $user->role === UserRole::FoAdmin
            && $user->field_office_id === $assignment->personnel?->field_office_id;
    }
}
