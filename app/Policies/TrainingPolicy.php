<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TrainingsView);
    }

    /**
     * Mirrors Training::scopeVisibleTo(): the list is filtered, so the detail
     * endpoint has to be too, or an office could open another's training by
     * guessing its id. A regional training (no field office) is visible to
     * everyone by design — that is what the null means.
     */
    public function view(User $user, Training $training): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->role->isRegionWide()
            || $training->isRegional()
            || in_array($training->field_office_id, $user->scopedFieldOfficeIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TrainingsManage);
    }

    public function update(User $user, Training $training): bool
    {
        return $this->manage($user, $training);
    }

    /**
     * Deleting is held apart from editing: a training carries the assignment
     * and attendance history behind members' service records.
     */
    public function delete(User $user, Training $training): bool
    {
        return $user->hasPermission(Permission::TrainingsDelete);
    }

    public function complete(User $user, Training $training): bool
    {
        return $this->manage($user, $training);
    }

    /**
     * Deliberately narrower than view(): a regional training is visible to
     * every office but owned by none, so editing and completing it stay with
     * the region. An office still rosters its own members onto it — that is
     * guarded per-member in TrainingAssignmentController.
     */
    private function manage(User $user, Training $training): bool
    {
        if (! $user->hasPermission(Permission::TrainingsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($training->field_office_id, $user->scopedFieldOfficeIds(), true);
    }
}
