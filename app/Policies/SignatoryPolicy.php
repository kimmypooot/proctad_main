<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Signatory;
use App\Models\User;

class SignatoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SignatoriesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SignatoriesManage);
    }

    public function update(User $user, Signatory $signatory): bool
    {
        return $this->manage($user, $signatory);
    }

    public function delete(User $user, Signatory $signatory): bool
    {
        return $this->manage($user, $signatory);
    }

    /**
     * A field office may only touch its own signatory — never the region-wide
     * default, which has no office and is whose signature appears when an
     * office has none of its own.
     */
    private function manage(User $user, Signatory $signatory): bool
    {
        if (! $user->hasPermission(Permission::SignatoriesManage)) {
            return false;
        }

        return $user->role->isRegionWide()
            || ($signatory->field_office_id !== null
                && $signatory->field_office_id === $user->field_office_id);
    }
}
