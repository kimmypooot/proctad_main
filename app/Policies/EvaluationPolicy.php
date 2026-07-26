<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::EvaluationsView);
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        if (! $user->hasPermission(Permission::EvaluationsView)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($evaluation->field_office_id, $user->scopedFieldOfficeIds(), true);
    }
}
