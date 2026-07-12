<?php

namespace App\Notifications;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification for the staff member who requested a certificate,
 * once an approver has released or disapproved it.
 */
class CertificateDecided extends Notification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->certificate->loadMissing('member:id,first_name,middle_name,last_name,suffix');
        $released = $this->certificate->status === CertificateStatus::Released;

        return [
            'title' => $released
                ? "{$this->certificate->type->label()} released"
                : "{$this->certificate->type->label()} disapproved",
            'body' => $released
                ? "{$this->certificate->member->name}'s {$this->certificate->type->label()} was approved and emailed."
                : "{$this->certificate->member->name}'s {$this->certificate->type->label()} request was disapproved: {$this->certificate->disapproval_remarks}",
            'url' => route('certificates.index'),
        ];
    }
}
