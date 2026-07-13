<?php

namespace App\Notifications;

use App\Models\ExamAssignment;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification to a member's linked self-service account when
 * they're assigned a venue for an examination — the in-system half of the
 * "notified through both email and the system" requirement (the email half
 * is the confirmation-link email sent alongside this, see
 * AssignmentConfirmationSender).
 */
class VenueAssigned extends Notification
{
    public function __construct(private readonly ExamAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->assignment->loadMissing('examination:id,title', 'examinationSchool.school:id,name');

        $venue = $this->assignment->examinationSchool?->school?->name;

        return [
            'title' => 'New examination assignment',
            'body' => "You've been assigned as {$this->assignment->role->label()} for {$this->assignment->examination->title}"
                .($venue ? " at {$venue}" : '').'. Please confirm your availability.',
            'url' => route('my.service-history'),
        ];
    }
}
