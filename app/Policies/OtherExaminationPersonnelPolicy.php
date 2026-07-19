<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OtherExaminationPersonnel;
use App\Models\User;

class OtherExaminationPersonnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, OtherExaminationPersonnel $oep): bool
    {
        if ($user->role->isRegionWide()) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $oep->field_office_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function update(User $user, OtherExaminationPersonnel $oep): bool
    {
        return $this->manage($user, $oep);
    }

    public function delete(User $user, OtherExaminationPersonnel $oep): bool
    {
        return $this->manage($user, $oep);
    }

    private function manage(User $user, OtherExaminationPersonnel $oep): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $oep->field_office_id;
    }
}
