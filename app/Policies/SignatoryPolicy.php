<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Signatory;
use App\Models\User;

class SignatoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector);
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
     * ESD / Super Admin manage any entry (spec 4.3); FO Admins only entries
     * of their own Field Office (spec 4.4) — never region-wide defaults.
     *
     * Deliberately NOT widened to the shared-jurisdiction office set the way
     * member and record scoping is. A signatory is the office's own signing
     * official, not something the jurisdiction shares: Leyte I and Leyte II
     * serve the same testing center but sign their own certificates, and
     * neither should be able to edit the other's signature block.
     */
    private function manage(User $user, Signatory $signatory): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $signatory->field_office_id !== null
            && $signatory->field_office_id === $user->field_office_id;
    }
}
