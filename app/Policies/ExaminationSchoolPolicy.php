<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExaminationSchool;
use App\Models\User;

class ExaminationSchoolPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ExaminationSchoolsManage);
    }

    public function delete(User $user, ExaminationSchool $venue): bool
    {
        if (! $user->hasPermission(Permission::ExaminationSchoolsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || (bool) $venue->school?->handledByOffice($user->field_office_id);
    }
}
