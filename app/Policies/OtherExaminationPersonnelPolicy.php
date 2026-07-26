<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\OtherExaminationPersonnel;
use App\Models\User;

class OtherExaminationPersonnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::OepView);
    }

    public function view(User $user, OtherExaminationPersonnel $oep): bool
    {
        if (! $user->hasPermission(Permission::OepView)) {
            return false;
        }

        return $user->role->isRegionWide() || $oep->isWithinJurisdictionOf($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OepManage);
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
        if (! $user->hasPermission(Permission::OepManage)) {
            return false;
        }

        return $user->role->isRegionWide() || $oep->isWithinJurisdictionOf($user);
    }
}
