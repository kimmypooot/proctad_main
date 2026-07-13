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
     * Approve or disapprove: the certificate type's primary approver role
     * (Field Director within their own Testing Center for Appearance/
     * Designation Order, Management region-wide for Appreciation), plus two
     * fallback paths for when the primary approver is unavailable — Super
     * Admin/ESD Admin region-wide for any type, and Field Director locally
     * for any type originating in their own Testing Center.
     */
    public function decide(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Pending) {
            return false;
        }

        $approverRole = $certificate->type->approverRole();

        if ($approverRole === null) {
            return false;
        }

        if ($user->role === $approverRole) {
            return $user->role !== UserRole::FieldDirector
                || $user->field_office_id === $certificate->field_office_id;
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::EsdAdmin], true)) {
            return true;
        }

        return $user->role === UserRole::FieldDirector
            && $user->field_office_id === $certificate->field_office_id;
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
