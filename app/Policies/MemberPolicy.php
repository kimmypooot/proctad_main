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

    /**
     * Members are seen by testing center, not by field office: Leyte I and
     * Leyte II jointly serve Tacloban City, so staff of either must see the
     * whole roster there.
     *
     * Regional-office members are visible to every office but managed by none —
     * they serve region-wide, so any office may need to assign them, while
     * editing their record stays with region-wide roles (see manage()).
     */
    public function view(User $user, Member $member): bool
    {
        if ($member->user_id === $user->id) {
            return true;
        }

        if ($user->role->isRegionWide()) {
            return true;
        }

        if (! $user->role->isFieldOfficeScoped()) {
            return false;
        }

        return $member->isRegionWide() || $this->sharesJurisdiction($user, $member);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
    }

    public function update(User $user, Member $member): bool
    {
        return $this->manage($user, $member);
    }

    public function delete(User $user, Member $member): bool
    {
        return $this->manage($user, $member);
    }

    /**
     * Editing is narrower than viewing: a regional-office member belongs to no
     * field office's roster, so no field office may change their record even
     * though every office can see and assign them.
     */
    private function manage(User $user, Member $member): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && ! $member->isRegionWide()
            && $this->sharesJurisdiction($user, $member);
    }

    /**
     * Whether the member serves a testing center this user's office covers.
     * A member with no center yet (their office handles several and nobody has
     * placed them) is nobody's to manage until staff assign one.
     */
    private function sharesJurisdiction(User $user, Member $member): bool
    {
        return $member->testing_center_id !== null
            && in_array($member->testing_center_id, $user->scopedTestingCenterIds(), true);
    }
}
