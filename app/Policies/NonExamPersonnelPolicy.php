<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\NonExamPersonnel;
use App\Models\User;

class NonExamPersonnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, NonExamPersonnel $nep): bool
    {
        if ($user->role->isRegionWide()) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $nep->field_office_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin);
    }

    public function update(User $user, NonExamPersonnel $nep): bool
    {
        return $this->manage($user, $nep);
    }

    public function delete(User $user, NonExamPersonnel $nep): bool
    {
        return $this->manage($user, $nep);
    }

    private function manage(User $user, NonExamPersonnel $nep): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role === UserRole::FoAdmin
            && $user->field_office_id === $nep->field_office_id;
    }
}
