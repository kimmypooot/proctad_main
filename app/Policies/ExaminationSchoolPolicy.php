<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\User;

/**
 * Venue/room management follows the same authority as assigning members
 * (legacy: testing-center panel manages both schools-as-venues and rooms).
 */
class ExaminationSchoolPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function delete(User $user, ExaminationSchool $venue): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $venue->school?->field_office_id;
    }
}
