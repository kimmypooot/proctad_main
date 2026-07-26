<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Blacklist;
use App\Models\Member;
use App\Models\User;

class BlacklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::BlacklistsView);
    }

    public function create(User $user, Member $member): bool
    {
        return $this->manage($user, $member->testing_center_id);
    }

    public function lift(User $user, Blacklist $blacklist): bool
    {
        return $this->manage($user, $blacklist->testing_center_id);
    }

    /**
     * A member with no testing center is nobody's to blacklist locally — only a
     * region-wide role can act on them.
     */
    private function manage(User $user, ?int $testingCenterId): bool
    {
        if (! $user->hasPermission(Permission::BlacklistsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || ($testingCenterId !== null
                && in_array($testingCenterId, $user->scopedTestingCenterIds(), true));
    }
}
