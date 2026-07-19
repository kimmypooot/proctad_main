<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\User;

class ExamAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function update(User $user, ExamAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function delete(User $user, ExamAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    private function manage(User $user, ExamAssignment $assignment): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $assignment->field_office_id;
    }
}
