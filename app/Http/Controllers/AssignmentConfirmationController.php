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
use Illuminate\Support\Carbon;
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

        $log = $sender->send($assignment, $request->user());

        if ($log === null) {
            return back()->with('error', 'This member has no email address on file.');
        }

        // NotificationMailer swallows delivery exceptions into the log rather than
        // throwing, so a returned log is not proof of delivery — report the real
        // outcome instead of flashing success over a silent failure.
        if ($log->status === 'failed') {
            return back()->with('error', "Could not send the confirmation to {$assignment->member->name}: {$log->error_message}");
        }

        // Deliberate suppression, not a failure — the assignment still advances so
        // the workflow stays testable, but say plainly that nothing was delivered.
        if ($log->status === 'skipped') {
            return back()->with('error', "Email sending is switched off in Settings, so nothing was sent to {$assignment->member->name}. The assignment was still marked as awaiting confirmation.");
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
        $assignment->loadMissing('member', 'examination', 'examinationSchool.school');

        return Inertia::render('Assignments/Confirm', [
            'assignment' => $this->present($assignment),
            'actionUrl' => $request->fullUrl(),
            // The signed link's own expiry *is* the response deadline, so read
            // it back off the URL rather than storing a second copy that could
            // drift out of step with LINK_LIFETIME_DAYS.
            'responseDueBy' => $request->query('expires')
                ? Carbon::createFromTimestamp((int) $request->query('expires'))
                    ->timezone(config('app.timezone'))
                    ->format('F j, Y')
                : null,
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
            // Responses are deliberately one-shot. Say where to go instead of
            // leaving the member at a dead end with no recourse.
            return back()->with('error', 'You have already responded to this assignment. To change your response, please contact your Testing Center.');
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
            // No 'room' by design: the member is told their room in person by
            // the secretariat on exam day, so it must not reach this public
            // page — not rendered, and not in the payload either.
            'decline_reason' => $assignment->decline_reason,
        ];
    }
}
