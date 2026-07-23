<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Enums\ExamRole;
use App\Http\Middleware\ResolveScannerSession;
use App\Models\AuditLog;
use App\Models\ExamAssignment;
use App\Models\ExamAssignmentAttendance;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\Member;
use App\Models\OepAssignment;
use App\Models\OepAttendance;
use App\Models\OtherExaminationPersonnel;
use App\Models\ScannerSession;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Services\AlternateActivator;
use App\Services\CertificateService;
use App\Services\TestAdministratorServiceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    /** @var array<array{CertificateType, ExamAssignment|TrainingAssignment, User}> */
    private array $pendingCertificates = [];

    public function __invoke(Request $request): Response
    {
        $session = $this->session($request);
        $user = $this->actor($request);
        $raw = $this->normalize($request->string('code')->trim());

        // A public scanner link is pinned to its own event and venue. Reading
        // these from the session rather than the query string is what stops a
        // leaked link from being re-pointed at another examination by editing
        // the URL.
        if ($session) {
            $examinationId = $session->examination_id;
            $trainingId = $session->training_id;
            $venueId = $session->examination_school_id;
        } else {
            $examinationId = $request->integer('examination_id') ?: null;
            $trainingId = $request->integer('training_id') ?: null;
            $venueId = $request->integer('examination_school_id') ?: null;
        }

        $result = null;
        $oepResult = null;
        $notFound = false;
        $attendance = null;
        $venues = [];

        if ($raw['type'] === 'oep') {
            $oep = OtherExaminationPersonnel::with('fieldOffice:id,name,code')
                ->where('oep_id', $raw['code'])
                ->when($user->role->isFieldOfficeScoped(),
                    fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->first();

            $oepResult = $oep ? [
                'id' => $oep->id,
                'oep_id' => $oep->oep_id,
                'name' => $oep->name,
                'personnel_type_label' => $oep->personnel_type->label(),
                'field_office' => $oep->fieldOffice?->name,
                'is_active' => $oep->is_active,
            ] : null;
            $notFound = $oep === null;

            if ($oep && $venueId) {
                $attendance = $this->confirmOepAttendance($oep, $venueId, $user);
            } elseif ($oep && $examinationId) {
                $attendance = ['outcome' => 'venue_required'];
            }

            if ($oepResult && $attendance) {
                $oepResult['venue'] = $attendance['venue'] ?? null;
            }
        } elseif ($raw['code'] !== '') {
            $member = Member::with('fieldOffice:id,name,code')
                ->where('proctad_id', $raw['code'])
                // FO Admins can only look up members of their own Testing Center.
                ->when($user->role->isFieldOfficeScoped(),
                    fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->first();

            // Public sessions get identity only — enough to confirm the right
            // person is standing there. Employment and membership standing are
            // staff-only: a scanner link is shared around a venue and cannot
            // be treated as a confidential channel.
            $result = $member ? array_filter([
                'id' => $member->id,
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'agency' => $session ? null : $member->agency,
                'field_office' => $member->fieldOffice?->name,
                'status' => $session ? null : $member->status->value,
                'status_label' => $session ? null : $member->status->label(),
                'status_variant' => $session ? null : $member->status->badgeVariant(),
            ], fn ($value) => $value !== null) : null;
            $notFound = $member === null;

            if ($member && $examinationId) {
                $attendance = $this->confirmExamAttendance($member, $examinationId, $venueId, $user);
            } elseif ($member && $trainingId) {
                $attendance = $this->confirmTrainingAttendance($member, $trainingId, $user);
            }

            // Surface the assignment's venue/room/designation on the identity
            // card too, not just inside the transient attendance-outcome banner.
            if ($result && $attendance) {
                $result['venue'] = $attendance['venue'] ?? null;
                $result['room'] = $attendance['room'] ?? null;
                $result['designation'] = $attendance['designation'] ?? null;
            }

            // Service history is only surfaced here, at the point of a live QR
            // scan during an actual examination — not exposed for identity-only
            // lookups, training scans, or public scanner links.
            if ($result && $examinationId && ! $session) {
                $result['service_history'] = TestAdministratorServiceHistory::forMember($member);
            }
        }

        if ($examinationId) {
            $venues = ExaminationSchool::where('examination_id', $examinationId)
                ->with('school:id,name')
                ->get()
                ->map(fn (ExaminationSchool $venue) => ['value' => $venue->id, 'label' => $venue->school?->name]);
        }

        $response = Inertia::render('Scanner/Index', [
            'code' => $raw['code'],
            'examinationId' => $examinationId,
            'trainingId' => $trainingId,
            'examinationSchoolId' => $venueId,
            'result' => $result,
            'oepResult' => $oepResult,
            'notFound' => $notFound,
            'attendance' => $attendance,
            'venues' => $venues,
            // A public session's context is fixed, so the event/venue pickers
            // have nothing to offer — send empty options rather than a menu of
            // every other examination.
            'events' => $session ? ['examinations' => [], 'trainings' => []] : $this->eventOptions($user),
            'attendanceSummary' => $this->attendanceSummary($examinationId, $trainingId, $venueId, $user),
            'publicSession' => $session ? [
                // Namespaces the offline scan queue in localStorage, so scans
                // queued here can never be replayed against another event.
                'token' => $session->token,
                'label' => $session->label,
                'event' => $session->examination?->title ?? $session->training?->title,
                'venue' => $session->examinationSchool?->school?->name,
                'expires_at' => $session->expires_at->format('M d, Y H:i'),
                // The formatted string above is for display only. Parsing it in
                // JS reads it against the browser's timezone, not the app's, so
                // the shell's "expiring soon" check gets an unambiguous ISO
                // timestamp with an offset instead.
                'expires_at_iso' => $session->expires_at->toIso8601String(),
                'issued_by' => $session->creator?->name,
                'scan_url' => route('scan', $session->token),
                'mark_attendance_url' => route('scan.mark-attendance', $session->token),
            ] : null,
        ]);

        if ($session && $raw['code'] !== '') {
            $this->recordSessionScan($session);
        }

        $this->deferPendingCertificates();

        return $response;
    }

    /**
     * Issue the certificates this request earned *after* the response is sent.
     *
     * generatePending() can mint a number, render a PDF and send mail — and for
     * an auto-released type (Completion) it always does. Doing that inline
     * makes the operator wait on SMTP mid-scan, and a bulk mark of a full TEA
     * roster does it once per person, which is enough to time the request out.
     */
    private function deferPendingCertificates(): void
    {
        if (! $this->pendingCertificates) {
            return;
        }

        $certificates = $this->certificates;
        $pending = $this->pendingCertificates;
        $this->pendingCertificates = [];

        app()->terminating(function () use ($certificates, $pending) {
            foreach ($pending as [$type, $assignment, $user]) {
                $certificates->generatePending($type, $assignment, $user);
            }
        });
    }

    /**
     * Bulk manual attendance fallback — mirrors legacy's searchable multi-select
     * modal for members (and, at a selected venue, other examination
     * personnel) whose QR won't scan. Silently skips anyone already marked
     * present rather than failing the whole batch.
     */
    /**
     * Exam-day cover from the venue itself: declare a seat vacant, then call an
     * Alternate Examiner into it. Shares AlternateActivator with the admin
     * console so neither front door can drift from the other's rules.
     *
     * Deliberately NOT part of the offline scan queue (useScanQueue). A scan is
     * an observation and replays safely late; declaring someone absent is a
     * judgement about a moment, and a queued one syncing an hour later could
     * strip a seat from somebody who has since arrived and been scanned in.
     * These require connectivity, which is the honest constraint.
     *
     * A public link is pinned to its own examination and venue exactly as
     * scanning is — the posted assignment must belong to them.
     */
    public function markAbsent(Request $request, AlternateActivator $activator): RedirectResponse
    {
        $user = $this->actor($request);

        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
        ]);

        $assignment = $this->coverableAssignment($request, $validated['assignment_id']);

        Gate::forUser($user)->authorize('update', $assignment);

        return $activator->markAbsent($assignment, $user)
            ? back()->with('success', "{$assignment->member?->name} marked absent.")
            : back()->with('error', 'Already marked absent, or this person has already been scanned in.');
    }

    public function activateAlternate(Request $request, AlternateActivator $activator): RedirectResponse
    {
        $user = $this->actor($request);

        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
            'alternate_assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
        ]);

        $vacant = $this->coverableAssignment($request, $validated['assignment_id']);
        $alternate = $this->coverableAssignment($request, $validated['alternate_assignment_id']);

        Gate::forUser($user)->authorize('update', $vacant);
        Gate::forUser($user)->authorize('update', $alternate);

        if ($refusal = $activator->cannotActivate($alternate, $vacant)) {
            return back()->with('error', $refusal);
        }

        $activator->activate($alternate, $vacant);

        return back()->with('success', sprintf(
            '%s is now serving as %s in place of %s.',
            $alternate->member?->name,
            $vacant->role->label(),
            $vacant->member?->name,
        ));
    }

    /**
     * An assignment this scanner may act on, or 404. On a public link that
     * means the session's own examination and venue — the same pinning the
     * scan path applies, so a leaked token cannot reach another venue's roster.
     */
    private function coverableAssignment(Request $request, int $assignmentId): ExamAssignment
    {
        $session = $this->session($request);

        return ExamAssignment::query()
            ->whereKey($assignmentId)
            ->when($session, fn ($q) => $q
                ->where('examination_id', $session->examination_id)
                ->where('examination_school_id', $session->examination_school_id))
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
            ->firstOrFail();
    }

    public function bulkMarkAttendance(Request $request): RedirectResponse
    {
        $session = $this->session($request);
        $user = $this->actor($request);

        $validated = $request->validate([
            'type' => ['required', 'in:training,exam'],
            'training_id' => ['required_if:type,training', 'nullable', 'integer', 'exists:trainings,id'],
            'examination_id' => ['required_if:type,exam', 'nullable', 'integer', 'exists:examinations,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'oep_assignment_ids' => ['nullable', 'array'],
            'oep_assignment_ids.*' => ['integer', 'exists:oep_assignments,id'],
            'covered_attendance_ids' => ['nullable', 'array'],
            'covered_attendance_ids.*' => ['string', 'regex:/^\d+:\d+$/'],
        ]);

        // Same rule as the scan itself: a public link marks people present at
        // its own event, whatever the posted body claims.
        if ($session) {
            $validated['type'] = $session->training_id ? 'training' : 'exam';
            $validated['training_id'] = $session->training_id;
            $validated['examination_id'] = $session->examination_id;
        }

        $memberIds = $validated['member_ids'] ?? [];
        $oepAssignmentIds = $validated['oep_assignment_ids'] ?? [];
        $coveredAttendanceIds = $validated['covered_attendance_ids'] ?? [];

        // Covered-school rows name their own venue in the "{assignment}:{school}"
        // pair, so a session must also pin that half to its own venue.
        if ($session) {
            $coveredAttendanceIds = array_values(array_filter(
                $coveredAttendanceIds,
                fn (string $pair) => (int) Str::after($pair, ':') === $session->examination_school_id,
            ));
        }

        abort_if(! $memberIds && ! $oepAssignmentIds && ! $coveredAttendanceIds, 422, 'Select at least one person to mark present.');

        $markedCount = 0;

        if ($memberIds) {
            if ($validated['type'] === 'training') {
                $query = TrainingAssignment::where('training_id', $validated['training_id']);
                $certificateTypes = $this->trainingCertificateTypes(Training::find($validated['training_id']));
            } else {
                $query = ExamAssignment::where('examination_id', $validated['examination_id']);
                $certificateTypes = [CertificateType::Appreciation];
            }

            $assignments = $query
                ->whereIn('member_id', $memberIds)
                ->whereNull('attendance_confirmed_at')
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->update(['attendance_confirmed_at' => now(), 'attendance_confirmed_by' => $user->id]);

                foreach ($certificateTypes as $certificateType) {
                    $this->pendingCertificates[] = [$certificateType, $assignment, $user];
                }
            }

            $markedCount += $assignments->count();
        }

        if ($oepAssignmentIds) {
            $oepAssignments = OepAssignment::whereIn('id', $oepAssignmentIds)
                ->when($session, fn ($q) => $q->where('examination_school_id', $session->examination_school_id))
                ->with('personnel')
                ->get();

            foreach ($oepAssignments as $assignment) {
                // forUser, not the ambient Gate: on a public scanner link there
                // is no authenticated user, and the actor is the staff member
                // who issued the link.
                Gate::forUser($user)->authorize('update', $assignment);

                $exists = OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
                    ->where('examination_school_id', $assignment->examination_school_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                OepAttendance::create([
                    'other_examination_personnel_id' => $assignment->other_examination_personnel_id,
                    'examination_school_id' => $assignment->examination_school_id,
                    'status' => 'present',
                    'scan_method' => 'manual',
                    'scanned_at' => now(),
                    'scanned_by' => $user->id,
                ]);

                $markedCount++;
            }
        }

        if ($coveredAttendanceIds) {
            foreach ($coveredAttendanceIds as $pair) {
                [$assignmentId, $schoolId] = array_map('intval', explode(':', $pair, 2));

                $assignment = ExamAssignment::where('id', $assignmentId)
                    ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                    ->first();

                if (! $assignment) {
                    continue;
                }

                Gate::forUser($user)->authorize('update', $assignment);

                $isCovered = $assignment->coveredSchools()->wherePivot('examination_school_id', $schoolId)->exists();

                if (! $isCovered) {
                    continue;
                }

                $exists = ExamAssignmentAttendance::where('exam_assignment_id', $assignment->id)
                    ->where('examination_school_id', $schoolId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                ExamAssignmentAttendance::create([
                    'exam_assignment_id' => $assignment->id,
                    'examination_school_id' => $schoolId,
                    'status' => 'present',
                    'scan_method' => 'manual',
                    'scanned_at' => now(),
                    'scanned_by' => $user->id,
                ]);

                $markedCount++;
            }
        }

        $this->deferPendingCertificates();

        return back()->with('success', "Marked {$markedCount} person(s) present.");
    }

    /**
     * The public scanner session behind this request, if any — set by
     * ResolveScannerSession on the /scan/{token} routes and absent on the
     * authenticated /scanner route.
     */
    private function session(Request $request): ?ScannerSession
    {
        $session = $request->attributes->get(ResolveScannerSession::ATTRIBUTE);

        return $session instanceof ScannerSession ? $session : null;
    }

    /**
     * Who this scan is attributed to and scoped by. On a public scanner link
     * that is the staff member who issued it: attendance rows, certificate
     * queueing and field-office scoping all need a real user, and the issuer
     * is the person actually accountable for the link being out there.
     */
    private function actor(Request $request): User
    {
        return $this->session($request)?->creator ?? $request->user();
    }

    /**
     * Usage counters for the admin's session list, plus an audit row so a scan
     * made through a shared link is distinguishable from one the issuer made
     * while signed in. Written with the query builder to keep Auditable's
     * model events off the per-scan path.
     */
    private function recordSessionScan(ScannerSession $session): void
    {
        ScannerSession::whereKey($session->id)->update([
            'last_used_at' => now(),
            'scan_count' => $session->scan_count + 1,
        ]);

        AuditLog::create([
            'user_id' => $session->created_by,
            'action' => 'scanner_session_scan',
            'auditable_type' => ScannerSession::class,
            'auditable_id' => $session->id,
            'field_office_id' => $session->field_office_id,
            'changes' => ['label' => $session->label],
        ]);
    }

    /**
     * Live attendance summary (Total / Present / Absent + recent scans +
     * not-yet-present roster for the manual fallback) for the selected
     * training or examination context — mirrors legacy's stats strip and
     * "Recent Attendance" table on the training QR scanner page. In exam
     * mode with a venue selected, other examination personnel assigned to
     * that venue are folded into the same summary/roster (their attendance
     * is tracked per venue, not per whole examination — see
     * OepAssignmentController).
     */
    private function attendanceSummary(?int $examinationId, ?int $trainingId, ?int $venueId, User $user): ?array
    {
        if ($trainingId) {
            $assignments = TrainingAssignment::with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
                ->where('training_id', $trainingId)
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
        } elseif ($examinationId) {
            $assignments = ExamAssignment::with([
                'member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'examinationSchool.school',
                'room',
            ])
                ->where('examination_id', $examinationId)
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
        } else {
            return null;
        }

        // "Awaiting", not "absent": these are people who have not been scanned
        // *yet*. Real absence is a deliberate judgement recorded separately
        // (marked_absent_at) and is what lets an alternate be called in — see
        // AlternateActivator. Conflating the two here would make the stats
        // strip claim a room is abandoned five minutes after doors open.
        [$present, $awaiting] = $assignments->partition(fn ($a) => $a->attendance_confirmed_at !== null);

        $recent = $present->map(fn ($a) => [
            'id' => "member:{$a->id}",
            'name' => $a->member?->name,
            'code' => $a->member?->proctad_id,
            'venue' => $examinationId ? $a->examinationSchool?->school?->name : null,
            'room' => $examinationId ? $a->room?->room_number : null,
            'designation' => $examinationId ? $a->room?->designation : null,
            'confirmed_at_raw' => $a->attendance_confirmed_at,
        ]);
        $roster = $awaiting->values()
            ->map(fn ($a) => [
                'value' => "member:{$a->member_id}",
                'label' => "{$a->member?->name} ({$a->member?->proctad_id})",
                'code' => $a->member?->proctad_id,
                'venue' => $examinationId ? $a->examinationSchool?->school?->name : null,
                'room' => $examinationId ? $a->room?->room_number : null,
                'designation' => $examinationId ? $a->room?->designation : null,
            ]);

        $total = $assignments->count();
        $presentCount = $present->count();
        $awaitingCount = $awaiting->count();

        if ($examinationId && $venueId) {
            $venueName = ExaminationSchool::with('school')->find($venueId)?->school?->name;

            $oepAssignments = OepAssignment::where('examination_school_id', $venueId)
                ->with('personnel:id,oep_id,first_name,middle_name,last_name,suffix')
                ->get();
            $oepAttendance = OepAttendance::where('examination_school_id', $venueId)
                ->get()
                ->keyBy('other_examination_personnel_id');

            [$oepPresent, $oepAwaiting] = $oepAssignments->partition(
                fn (OepAssignment $a) => $oepAttendance->has($a->other_examination_personnel_id),
            );

            $total += $oepAssignments->count();
            $presentCount += $oepPresent->count();
            $awaitingCount += $oepAwaiting->count();

            $recent = $recent->concat($oepPresent->map(fn (OepAssignment $a) => [
                'id' => "oep:{$a->id}",
                'name' => $a->personnel?->name,
                'code' => $a->personnel?->oep_id,
                'venue' => $venueName,
                'room' => null,
                'confirmed_at_raw' => $oepAttendance->get($a->other_examination_personnel_id)->scanned_at,
            ]));

            $roster = $roster
                ->concat($oepAwaiting->values()->map(fn (OepAssignment $a) => [
                    'value' => "oep:{$a->id}",
                    'label' => "{$a->personnel?->name} ({$a->personnel?->oep_id}) · Other Examination Personnel",
                    'code' => $a->personnel?->oep_id,
                    'venue' => $venueName,
                    'room' => null,
                ]));

            // REC/LEC/CE-for-Investigation assignments that list this venue as a
            // covered school (reference-only monitoring, not their testing
            // center) — tracked via exam_assignment_attendances, independent of
            // the assignment's own attendance_confirmed_at.
            $coverageAssignments = ExamAssignment::where('examination_id', $examinationId)
                ->whereHas('coveredSchools', fn ($q) => $q->where('examination_school.id', $venueId))
                ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
            $coverageAttendance = ExamAssignmentAttendance::where('examination_school_id', $venueId)
                ->whereIn('exam_assignment_id', $coverageAssignments->pluck('id'))
                ->get()
                ->keyBy('exam_assignment_id');

            [$coveragePresent, $coverageAwaiting] = $coverageAssignments->partition(
                fn (ExamAssignment $a) => $coverageAttendance->has($a->id),
            );

            $total += $coverageAssignments->count();
            $presentCount += $coveragePresent->count();
            $awaitingCount += $coverageAwaiting->count();

            $recent = $recent->concat($coveragePresent->map(fn (ExamAssignment $a) => [
                'id' => "covered:{$a->id}:{$venueId}",
                'name' => $a->member?->name,
                'code' => $a->member?->proctad_id,
                'venue' => $venueName,
                'room' => null,
                'confirmed_at_raw' => $coverageAttendance->get($a->id)->scanned_at,
            ]));

            $roster = $roster
                ->concat($coverageAwaiting->values()->map(fn (ExamAssignment $a) => [
                    'value' => "covered:{$a->id}:{$venueId}",
                    'label' => "{$a->member?->name} ({$a->member?->proctad_id}) · Covered School ({$a->role->label()})",
                    'code' => $a->member?->proctad_id,
                    'venue' => $venueName,
                    'room' => null,
                ]));
        }

        $recent = $recent->sortByDesc('confirmed_at_raw')->take(10)->values()
            ->map(fn ($entry) => [
                ...collect($entry)->except('confirmed_at_raw')->all(),
                'confirmed_at' => $entry['confirmed_at_raw']->format('M d, Y H:i'),
            ]);

        return [
            'total' => $total,
            'present' => $presentCount,
            'awaiting' => $awaitingCount,
            'recent' => $recent,
            'roster' => $roster,
            'cover' => $examinationId && $venueId ? $this->coverPanel($examinationId, $venueId, $user) : null,
        ];
    }

    /**
     * Exam-day cover for one venue: the seats that can be declared vacant, and
     * the Alternate Examiners on standby to fill them.
     *
     * Venue-scoped because the standby pool is — an alternate covers where they
     * are physically standing (AlternateActivator::cannotActivate enforces it).
     */
    private function coverPanel(int $examinationId, int $venueId, User $user): array
    {
        $assignments = ExamAssignment::query()
            ->where('examination_id', $examinationId)
            ->where('examination_school_id', $venueId)
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->with([
                'member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'room:id,room_number,designation',
                'coveringFor.member:id,first_name,middle_name,last_name,suffix',
                'coveredBy.member:id,first_name,middle_name,last_name,suffix',
            ])
            ->get();

        return [
            // Seats whose holder has not reported. Alternates are excluded:
            // a reserve who never turned up is not a seat needing cover.
            'seats' => $assignments
                ->filter(fn (ExamAssignment $a) => $a->attendance_confirmed_at === null
                    && $a->role !== ExamRole::AlternateExaminer
                    && ! $a->isSubstitute())
                ->values()
                ->map(fn (ExamAssignment $a) => [
                    'id' => $a->id,
                    'name' => $a->member?->name,
                    'code' => $a->member?->proctad_id,
                    'role_label' => $a->role->label(),
                    'room' => $a->room ? trim("{$a->room->designation} {$a->room->room_number}") : null,
                    'absent' => $a->isAbsent(),
                    'covered_by' => $a->coveredBy?->member?->name,
                ]),
            'alternates' => $assignments
                ->filter(fn (ExamAssignment $a) => $a->role === ExamRole::AlternateExaminer
                    && ! $a->isSubstitute()
                    && ! $a->isAbsent())
                ->values()
                ->map(fn (ExamAssignment $a) => [
                    'id' => $a->id,
                    'name' => $a->member?->name,
                    'code' => $a->member?->proctad_id,
                ]),
            'deployed' => $assignments
                ->filter(fn (ExamAssignment $a) => $a->isSubstitute())
                ->values()
                ->map(fn (ExamAssignment $a) => [
                    'id' => $a->id,
                    'name' => $a->member?->name,
                    'role_label' => $a->role->label(),
                    'covering_for' => $a->coveringFor?->member?->name,
                ]),
        ];
    }

    /**
     * Exam attendance via QR. A confirmed scan auto-queues the Certificate of
     * Appreciation for Management approval (user-confirmed flow, spec 2.3).
     *
     * REC/LEC/CE-for-Investigation assignments cover multiple schools but
     * staff only one testing center: scanning at their testing center
     * behaves exactly like any other role (confirms the assignment, queues
     * the certificate); scanning at one of their *covered* schools instead
     * records a separate per-school attendance row (no certificate
     * re-trigger, no duplicate on rescan) since the covered-school visit is
     * pre-determined logistics, not a second confirmation event.
     */
    private function confirmExamAttendance(Member $member, int $examinationId, ?int $venueId, User $user): array
    {
        $assignment = ExamAssignment::where('examination_id', $examinationId)
            ->where('member_id', $member->id)
            ->with(['examinationSchool.school', 'room'])
            ->first();

        if (! $assignment) {
            return ['outcome' => 'not_assigned'];
        }

        if ($assignment->isCoverageRole() && $venueId && $venueId !== $assignment->examination_school_id) {
            return $this->confirmCoveredSchoolAttendance($assignment, $venueId, $user);
        }

        // A school role staffs exactly one venue, so a scan at any other one is
        // someone presenting at the wrong gate — refuse rather than confirm
        // their attendance (and issue their certificate) from a venue they were
        // never deployed to. Assignments with no venue yet are exempt: there is
        // no "right" venue to be wrong about.
        if (! $assignment->isCoverageRole()
            && $venueId
            && $assignment->examination_school_id
            && $venueId !== $assignment->examination_school_id) {
            return [
                'outcome' => 'wrong_venue',
                'role_label' => $assignment->role->label(),
                'venue' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room?->room_number,
                'designation' => $assignment->room?->designation,
            ];
        }

        if ($assignment->isCoverageRole() && ! $venueId && ! $assignment->attendance_confirmed_at) {
            return [
                'outcome' => 'venue_required',
                'venue' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room?->room_number,
                'designation' => $assignment->room?->designation,
            ];
        }

        if ($assignment->attendance_confirmed_at) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
                'role_label' => $assignment->role->label(),
                'venue' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room?->room_number,
                'designation' => $assignment->room?->designation,
            ];
        }

        $assignment->update([
            'attendance_confirmed_at' => now(),
            'attendance_confirmed_by' => $user->id,
        ]);

        $this->pendingCertificates[] = [CertificateType::Appreciation, $assignment, $user];

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
            'role_label' => $assignment->role->label(),
            'venue' => $assignment->examinationSchool?->school?->name,
            'room' => $assignment->room?->room_number,
            'designation' => $assignment->room?->designation,
        ];
    }

    /**
     * Per-covered-school attendance for REC/LEC/CE-for-Investigation
     * assignments — pre-determined (no confirmation email/workflow), just
     * needs a scan/manual record per school actually visited.
     */
    private function confirmCoveredSchoolAttendance(ExamAssignment $assignment, int $venueId, User $user): array
    {
        $isCovered = $assignment->coveredSchools()->wherePivot('examination_school_id', $venueId)->exists();

        if (! $isCovered) {
            return ['outcome' => 'not_assigned'];
        }

        $coveredVenueName = ExaminationSchool::with('school')->find($venueId)?->school?->name;

        $attendance = ExamAssignmentAttendance::firstOrCreate(
            [
                'exam_assignment_id' => $assignment->id,
                'examination_school_id' => $venueId,
            ],
            [
                'status' => 'present',
                'scan_method' => 'qr',
                'scanned_at' => now(),
                'scanned_by' => $user->id,
            ],
        );

        if (! $attendance->wasRecentlyCreated) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
                'role_label' => $assignment->role->label(),
                'venue' => $coveredVenueName,
            ];
        }

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
            'role_label' => $assignment->role->label().' — covered school',
            'venue' => $coveredVenueName,
        ];
    }

    /**
     * Training attendance via QR. A confirmed scan auto-queues the Certificate
     * of Appearance for Field Director approval (user-confirmed flow), plus —
     * for a TEA — the Certificate of Completion, which needs no approver and
     * releases itself.
     */
    private function confirmTrainingAttendance(Member $member, int $trainingId, User $user): array
    {
        $assignment = TrainingAssignment::firstOrCreate(
            ['training_id' => $trainingId, 'member_id' => $member->id],
            ['field_office_id' => $member->field_office_id],
        );

        if ($assignment->attendance_confirmed_at) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
            ];
        }

        $assignment->update([
            'attendance_confirmed_at' => now(),
            'attendance_confirmed_by' => $user->id,
        ]);

        foreach ($this->trainingCertificateTypes($assignment->training) as $type) {
            $this->pendingCertificates[] = [$type, $assignment, $user];
        }

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
        ];
    }

    /**
     * What confirmed attendance at this training earns.
     *
     * @return array<CertificateType>
     */
    private function trainingCertificateTypes(?Training $training): array
    {
        return $training?->type?->issuesCompletionCertificate()
            ? [CertificateType::Appearance, CertificateType::Completion]
            : [CertificateType::Appearance];
    }

    /**
     * Other examination personnel attendance at a specific venue (OEP
     * attendance is tracked per examination_school, not per examination —
     * see OepAssignmentController).
     */
    private function confirmOepAttendance(OtherExaminationPersonnel $oep, int $venueId, User $user): array
    {
        $assignment = OepAssignment::where('examination_school_id', $venueId)
            ->where('other_examination_personnel_id', $oep->id)
            ->with('examinationSchool.school')
            ->first();

        if (! $assignment) {
            return ['outcome' => 'not_assigned'];
        }

        $venueName = $assignment->examinationSchool?->school?->name;

        $attendance = OepAttendance::firstOrCreate(
            [
                'other_examination_personnel_id' => $oep->id,
                'examination_school_id' => $venueId,
            ],
            [
                'status' => 'present',
                'scan_method' => 'qr',
                'scanned_at' => now(),
                'scanned_by' => $user->id,
            ],
        );

        if (! $attendance->wasRecentlyCreated) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
                'venue' => $venueName,
            ];
        }

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
            'venue' => $venueName,
        ];
    }

    /**
     * Grouped selector options: examinations and trainings.
     */
    private function eventOptions(User $user): array
    {
        return [
            'examinations' => Examination::orderByDesc('exam_date')->limit(10)
                ->get(['id', 'title', 'exam_date'])
                ->map(fn (Examination $exam) => [
                    'value' => $exam->id,
                    'label' => "{$exam->title} — {$exam->exam_date->format('M d, Y')}",
                ]),
            'trainings' => Training::whereNull('completed_at')
                ->when($user->role->isFieldOfficeScoped(),
                    fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->orderByDesc('training_date')->limit(10)
                ->get(['id', 'title', 'training_date'])
                ->map(fn (Training $training) => [
                    'value' => $training->id,
                    'label' => "{$training->title} — {$training->training_date->format('M d, Y')}",
                ]),
        ];
    }

    /**
     * Accept the raw PROCTAD ID, the full verification URL, an "OEP:{id}" QR
     * payload, or the inconsistent legacy formats still on printed QR stock
     * (e.g. "7|attendance", "PROCTAD-2026-XXXXX|attendance").
     *
     * @return array{type: 'member'|'oep', code: string}
     */
    private function normalize(string $code): array
    {
        $code = trim($code);

        if (Str::contains($code, '/verify/')) {
            return ['type' => 'member', 'code' => strtoupper(trim(Str::afterLast($code, '/verify/'), " /\t\n\r"))];
        }

        if (Str::startsWith(strtoupper($code), 'OEP:')) {
            return ['type' => 'oep', 'code' => strtoupper(trim(Str::after($code, ':')))];
        }

        // Legacy scanners appended "|attendance" (and similar suffixes) to the
        // raw code — strip anything from the first pipe onward.
        $code = Str::before($code, '|');
        $code = strtoupper(trim($code, " /\t\n\r"));

        return Str::startsWith($code, 'OEP-')
            ? ['type' => 'oep', 'code' => $code]
            : ['type' => 'member', 'code' => $code];
    }
}
