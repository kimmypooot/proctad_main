<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification to a member's linked self-service account when their
 * already-released certificate's PDF has been re-rendered — typically because
 * the letterhead or template changed.
 *
 * The certificate number, status and signatory are untouched, so this is not a
 * new issuance. It matters only because regeneration overwrites the stored file
 * in place: a member holding a downloaded copy now has one that differs from
 * the system's, under the same number, and nothing else would tell them.
 */
class CertificateReissued extends Notification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->certificate->type->label()} updated",
            'body' => "Your {$this->certificate->type->label()} ({$this->certificate->certificate_no}) has been "
                .'re-issued with an updated layout. The certificate number and its details are unchanged — '
                .'please download the current copy if you saved the previous one.',
            'url' => route('my.certificates'),
        ];
    }
}
