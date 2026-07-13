<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Enums\UserRole;
use App\Models\AssignmentConfirmation;
use App\Models\ExamAssignment;
use App\Models\User;
use App\Notifications\AssignmentDeclined;
use App\Services\AssignmentConfirmationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentConfirmationController extends Controller
{
    /**
     * Staff action: email the member a signed confirmation link (D5 — spec
     * requires assignment confirmation; audit recommended signed URLs over
     * stored plaintext tokens). Also used as "Resend" once already sent.
     */
    public function send(Request $request, ExamAssignment $assignment, AssignmentConfirmationSender $sender): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $sent = $sender->send($assignment, $request->user());

        if (! $sent) {
            return back()->with('error', 'This member has no email address on file.');
        }

        return back()->with('success', "Confirmation request sent to {$assignment->member->name}.");
    }

    /**
     * Public page opened from the emailed signed link. No login required —
     * the signature itself is the credential, exactly as for members who
     * have no password (Google-only accounts).
     */
    public function show(Request $request, ExamAssignment $assignment): Response
    {
        $assignment->loadMissing('member', 'examination', 'examinationSchool.school', 'room');

        return Inertia::render('Assignments/Confirm', [
            'assignment' => $this->present($assignment),
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Record the member's confirm/decline response.
     */
    public function store(Request $request, ExamAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'decline'])],
            'decline_reason' => ['required_if:action,decline', 'nullable', 'string', 'max:500'],
        ]);

        if ($assignment->status !== AssignmentStatus::Pending) {
            return back()->with('error', 'This assignment has already been responded to.');
        }

        $confirmed = $validated['action'] === 'confirm';

        $assignment->update([
            'status' => $confirmed ? AssignmentStatus::Confirmed : AssignmentStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $confirmed ? null : $validated['decline_reason'],
        ]);

        $assignment->confirmations()->create([
            'action' => $confirmed ? ConfirmationAction::Confirmed : ConfirmationAction::Declined,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'metadata' => $confirmed ? null : ['decline_reason' => $validated['decline_reason']],
        ]);

        if (! $confirmed) {
            $this->notifyFieldOffice($assignment);
        }

        return back()->with('success', $confirmed
            ? 'Thank you — your assignment is confirmed.'
            : 'Your response has been recorded. Thank you for letting us know.');
    }

    /**
     * Bell-notify the owning field office (fo_admin + field_director) that a
     * declined assignment needs re-staffing.
     */
    private function notifyFieldOffice(ExamAssignment $assignment): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::FoAdmin, UserRole::FieldDirector])
            ->where('field_office_id', $assignment->field_office_id)
            ->get();

        Notification::send($recipients, new AssignmentDeclined($assignment));
    }

    private function present(ExamAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'status' => $assignment->status->value,
            'status_label' => $assignment->status->label(),
            'member_name' => $assignment->member->name,
            'proctad_id' => $assignment->member->proctad_id,
            'role_label' => $assignment->role->label(),
            'exam_name' => $assignment->examination->title,
            'exam_date' => $assignment->examination->exam_date->format('F j, Y (l)'),
            'venue' => $assignment->examinationSchool?->school?->name,
            'room' => $assignment->room?->room_number,
            'decline_reason' => $assignment->decline_reason,
        ];
    }
}
