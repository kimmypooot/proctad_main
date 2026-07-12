<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification for approvers when a certificate enters the
 * pending queue (spec 2.3 approval routing). Database-only — the approver's
 * own review of the Approvals queue is the action, not an email.
 */
class CertificatePendingApproval extends Notification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->certificate->loadMissing('member:id,first_name,middle_name,last_name,suffix');

        return [
            'title' => "{$this->certificate->type->label()} pending approval",
            'body' => "{$this->certificate->member->name} — awaiting your decision.",
            'url' => route('approvals.index'),
        ];
    }
}
