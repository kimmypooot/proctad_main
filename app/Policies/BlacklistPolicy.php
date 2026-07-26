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
     * Director/Field Office Staff for a member within their jurisdiction —
     * which spans every office sharing their testing centers, so Leyte I and
     * Leyte II staff can act on the Tacloban City roster they jointly serve.
     */
    public function create(User $user, Member $member): bool
    {
        return $this->manage($user, $member->testing_center_id);
    }

    public function lift(User $user, Blacklist $blacklist): bool
    {
        return $this->manage($user, $blacklist->testing_center_id);
    }

    /**
     * Nullable: a member who is CSC staff has no testing center, and a record
     * with none is outside every field office's jurisdiction rather than inside
     * all of them.
     */
    private function manage(User $user, ?int $testingCenterId): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $testingCenterId !== null
            && in_array($testingCenterId, $user->scopedTestingCenterIds(), true);
    }
}
