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

    public function view(User $user, Training $training): bool
    {
        return $this->viewAny($user);
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

    private function manage(User $user, Training $training): bool
    {
        if (! $user->hasPermission(Permission::TrainingsManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($training->field_office_id, $user->scopedFieldOfficeIds(), true);
    }
}
