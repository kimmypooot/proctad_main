<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        if ($user->role->isRegionWide()) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && in_array($evaluation->field_office_id, $user->scopedFieldOfficeIds(), true);
    }
}
