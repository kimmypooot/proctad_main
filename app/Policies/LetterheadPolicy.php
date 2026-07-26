<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Letterhead is a region-wide branding asset (legacy: admin/superadmin/letter-head.php
 * only — never Field-Office scoped).
 */
class LetterheadPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasPermission(Permission::LetterheadsManage);
    }
}
