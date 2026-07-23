<?php

namespace App\Notifications;

use App\Enums\EligibilityRequirement;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification to a member's linked self-service account when a
 * Field Office reviews one of their eligibility requirements.
 *
 * Members can submit their own supporting documents but cannot mark themselves
 * compliant, so without this the outcome of a submission was invisible: the
 * member had to keep reopening their profile to find out whether anything had
 * happened. Sent for both outcomes — a verification and a rejection with
 * remarks are equally worth knowing about, and a rejection more so.
 */
class MemberRequirementReviewed extends Notification
{
    public function __construct(
        private readonly EligibilityRequirement $requirement,
        private readonly bool $complied,
        private readonly ?string $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->requirement->label();

        return [
            'title' => $this->complied
                ? "{$label} verified"
                : "{$label} needs attention",
            'body' => $this->complied
                ? "Your Field Office has verified your {$label}."
                : trim("Your {$label} has not been marked as complied. ".($this->remarks ?? '')),
            'url' => route('my.profile'),
        ];
    }
}
