<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OepAssignment;
use App\Models\User;

class OepAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function delete(User $user, OepAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function update(User $user, OepAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    private function manage(User $user, OepAssignment $assignment): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        $assignment->loadMissing('personnel');

        return $user->role->isFieldOfficeScoped()
            && (bool) $assignment->personnel?->isWithinJurisdictionOf($user);
    }
}
