<?php

namespace App\Notifications;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification to a member's linked self-service account when
 * their OWN certificate request is approved & released, or disapproved —
 * the in-system half of that outcome (the released/approval email is sent
 * separately by CertificateService::release()). Distinct from
 * CertificateDecided, which notifies the staff member who requested it.
 */
class MemberCertificateDecided extends Notification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $released = $this->certificate->status === CertificateStatus::Released;

        return [
            'title' => $released
                ? "{$this->certificate->type->label()} released"
                : "{$this->certificate->type->label()} disapproved",
            'body' => $released
                ? "Your {$this->certificate->type->label()} has been approved and is ready to download."
                : "Your {$this->certificate->type->label()} request was disapproved: {$this->certificate->disapproval_remarks}",
            'url' => route('my.certificates'),
        ];
    }
}
