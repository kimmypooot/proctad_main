<?php

namespace App\Notifications;

use App\Models\ExamAssignment;
use Illuminate\Notifications\Notification;

/**
 * In-app bell notification for the field office when a member declines an
 * exam assignment confirmation (D5) — they need re-staffing attention.
 */
class AssignmentDeclined extends Notification
{
    public function __construct(private readonly ExamAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->assignment->loadMissing('member:id,first_name,middle_name,last_name,suffix', 'examination:id,title');

        return [
            'title' => 'Assignment declined',
            'body' => "{$this->assignment->member->name} declined their {$this->assignment->role->label()} assignment for {$this->assignment->examination->title}.",
            'url' => route('examinations.show', $this->assignment->examination_id),
        ];
    }
}
