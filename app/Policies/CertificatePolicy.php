<?php

namespace App\Policies;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Member;
    }

    /**
     * Approve or disapprove: the certificate type's approver role, and for
     * FO-scoped approvers (Field Director) only within their own Testing Center.
     */
    public function decide(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Pending) {
            return false;
        }

        $approverRole = $certificate->type->approverRole();

        if ($approverRole === null || $user->role !== $approverRole) {
            // Super Admin may act as a fallback approver for any type.
            return $user->role === UserRole::SuperAdmin && $approverRole !== null;
        }

        if ($user->role === UserRole::FieldDirector) {
            return $user->field_office_id === $certificate->field_office_id;
        }

        return true;
    }

    /**
     * Download the released PDF: staff within scope, or the owning member.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Released) {
            return false;
        }

        if ($certificate->member?->user_id === $user->id) {
            return true;
        }

        if ($user->role->isRegionWide()) {
            return true;
        }

        return $user->role->isFieldOfficeScoped()
            && $user->field_office_id === $certificate->field_office_id;
    }
}
