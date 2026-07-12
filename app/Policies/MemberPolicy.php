<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, Member $member): bool
    {
        if ($member->user_id === $user->id) {
            return true;
        }

        if ($user->role->isRegionWide()) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $member->field_office_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin);
    }

    public function update(User $user, Member $member): bool
    {
        return $this->manage($user, $member);
    }

    public function delete(User $user, Member $member): bool
    {
        return $this->manage($user, $member);
    }

    private function manage(User $user, Member $member): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role === UserRole::FoAdmin
            && $user->field_office_id === $member->field_office_id;
    }
}
