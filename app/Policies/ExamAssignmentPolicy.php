<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExamAssignment;
use App\Models\User;

class ExamAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ExamAssignmentsManage);
    }

    public function update(User $user, ExamAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function delete(User $user, ExamAssignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    /**
     * Region-wide roles reach every center; the rest stay inside their own,
     * whatever the permission says.
     */
    private function manage(User $user, ExamAssignment $assignment): bool
    {
        if (! $user->hasPermission(Permission::ExamAssignmentsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($assignment->testing_center_id, $user->scopedTestingCenterIds(), true);
    }
}
