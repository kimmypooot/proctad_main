<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Blacklist;
use App\Models\Member;
use App\Models\User;

class BlacklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    /**
     * Blacklist a member: Super Admin/ESD Admin region-wide, or Field
     * Director/Testing Center Staff for a member in their own Testing Center.
     */
    public function create(User $user, Member $member): bool
    {
        return $this->manage($user, $member->field_office_id);
    }

    public function lift(User $user, Blacklist $blacklist): bool
    {
        return $this->manage($user, $blacklist->field_office_id);
    }

    private function manage(User $user, int $fieldOfficeId): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped() && $user->field_office_id === $fieldOfficeId;
    }
}
