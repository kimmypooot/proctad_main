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
     * Choose which office administers this center, and so whose signatory signs
     * for the members serving there. Region-wide roles only: the offices
     * sharing a center are peers, and letting either claim it would let one
     * put its own signature on the other's members' certificates.
     */
    public function designateAdministering(User $user, TestingCenter $testingCenter): bool
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
