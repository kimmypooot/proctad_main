<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function update(User $user, School $school): bool
    {
        return $this->manage($user, $school);
    }

    public function delete(User $user, School $school): bool
    {
        return $this->manage($user, $school);
    }

    private function manage(User $user, School $school): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $school->field_office_id;
    }
}
