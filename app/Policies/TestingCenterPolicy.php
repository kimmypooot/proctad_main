<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TestingCenter;
use App\Models\User;

class TestingCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function update(User $user, TestingCenter $testingCenter): bool
    {
        return $this->manage($user, $testingCenter);
    }

    public function delete(User $user, TestingCenter $testingCenter): bool
    {
        return $this->manage($user, $testingCenter);
    }

    /**
     * Choose which office receives new registrations at this center — the
     * hosting rotation. Region-wide roles only: the two offices sharing a
     * center are peers, so letting either reassign intake would let one take
     * the other's registrants unilaterally. Top management decides who hosts,
     * including when the same office hosts consecutive cycles.
     */
    public function designatePrimary(User $user, TestingCenter $testingCenter): bool
    {
        return $user->role->isRegionWide();
    }

    private function manage(User $user, TestingCenter $testingCenter): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $testingCenter->fieldOffices()->whereKey($user->field_office_id)->exists();
    }
}
