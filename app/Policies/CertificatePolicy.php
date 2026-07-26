<?php

namespace App\Policies;

use App\Enums\CertificateStatus;
use App\Enums\Permission;
use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CertificatesView);
    }

    /**
     * Approve or disapprove. Three things must hold, and only the first is
     * configurable: the role holds the permission, the certificate is still
     * Pending, and the user is either a designated approver for this type
     * (or a region-wide fallback for when that approver is unavailable) or is
     * acting within their own testing centers.
     */
    public function decide(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Pending) {
            return false;
        }

        if (! $user->hasPermission(Permission::CertificatesDecide)) {
            return false;
        }

        $approverRoles = $certificate->type->approverRoles();

        // A type nobody is designated to approve cannot be decided at all.
        if ($approverRoles === []) {
            return false;
        }

        if ($user->role->isRegionWide()) {
            return in_array($user->role, $approverRoles, true)
                || $user->hasPermission(Permission::CertificatesDecideAnyType);
        }

        return in_array($certificate->testing_center_id, $user->scopedTestingCenterIds(), true);
    }

    /**
     * Live, non-persisted preview render — same visibility scope as the
     * certificates list, available regardless of status so staff can check a
     * request before it's decided.
     */
    public function preview(User $user, Certificate $certificate): bool
    {
        if (! $user->hasPermission(Permission::CertificatesView)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($certificate->testing_center_id, $user->scopedTestingCenterIds(), true);
    }

    /**
     * Download the released PDF: the owning member always, or staff in scope.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Released) {
            return false;
        }

        if ($certificate->member?->user_id === $user->id) {
            return true;
        }

        return $this->preview($user, $certificate);
    }

    /**
     * Re-render an already-issued certificate's stored PDF (same number,
     * signatory, and release date — only the rendering is refreshed, e.g.
     * after a letterhead or template change).
     */
    public function regenerate(User $user, Certificate $certificate): bool
    {
        if ($certificate->status !== CertificateStatus::Released) {
            return false;
        }

        if (! $user->hasPermission(Permission::CertificatesRegenerate)) {
            return false;
        }

        return $user->role->isRegionWide()
            || in_array($certificate->testing_center_id, $user->scopedTestingCenterIds(), true);
    }
}
