<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TrainingAssignment;
use App\Models\User;

class TrainingAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TrainingAssignmentsManage);
    }

    public function update(User $user, TrainingAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function delete(User $user, TrainingAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    private function manage(User $user, TrainingAssignment $assignment): bool
    {
        if (! $user->hasPermission(Permission::TrainingAssignmentsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($assignment->testing_center_id, $user->scopedTestingCenterIds(), true);
    }
}
