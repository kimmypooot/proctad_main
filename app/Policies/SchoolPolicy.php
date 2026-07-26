<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SchoolsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SchoolsManage);
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
        if (! $user->hasPermission(Permission::SchoolsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || $school->handledByOffice($user->field_office_id);
    }
}
