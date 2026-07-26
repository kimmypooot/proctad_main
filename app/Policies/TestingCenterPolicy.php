<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TestingCenter;
use App\Models\User;

class TestingCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TestingCentersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TestingCentersManage);
    }

    public function update(User $user, TestingCenter $testingCenter): bool
    {
        return $this->manage($user, $testingCenter);
    }

    public function delete(User $user, TestingCenter $testingCenter): bool
    {
        return $this->manage($user, $testingCenter);
    }

    public function designateAdministering(User $user, TestingCenter $testingCenter): bool
    {
        return $user->hasPermission(Permission::TestingCentersDesignate);
    }

    private function manage(User $user, TestingCenter $testingCenter): bool
    {
        if (! $user->hasPermission(Permission::TestingCentersManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || $testingCenter->fieldOffices()->whereKey($user->field_office_id)->exists();
    }
}
