<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\ExamAssignmentAttendance;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\Member;
use App\Models\NepAssignment;
use App\Models\NepAttendance;
use App\Models\NonExamPersonnel;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $raw = $this->normalize($request->string('code')->trim());
        $examinationId = $request->integer('examination_id') ?: null;
        $trainingId = $request->integer('training_id') ?: null;
        $venueId = $request->integer('examination_school_id') ?: null;

        $result = null;
        $nepResult = null;
        $notFound = false;
        $attendance = null;
        $venues = [];

        if ($raw['type'] === 'nep') {
            $nep = NonExamPersonnel::with('fieldOffice:id,name,code')
                ->where('nep_id', $raw['code'])
                ->when($user->role === UserRole::FoAdmin,
                    fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->first();

            $nepResult = $nep ? [
                'id' => $nep->id,
                'nep_id' => $nep->nep_id,
                'name' => $nep->name,
                'personnel_type_label' => $nep->personnel_type->label(),
                'field_office' => $nep->fieldOffice?->name,
                'is_active' => $nep->is_active,
            ] : null;
            $notFound = $nep === null;

            if ($nep && $venueId) {
                $attendance = $this->confirmNepAttendance($nep, $venueId, $user);
            } elseif ($nep && $examinationId) {
                $attendance = ['outcome' => 'venue_required'];
            }
        } elseif ($raw['code'] !== '') {
            $member = Member::with('fieldOffice:id,name,code')
                ->where('proctad_id', $raw['code'])
                // FO Admins can only look up members of their own Testing Center.
                ->when($user->role === UserRole::FoAdmin,
                    fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->first();

            $result = $member ? [
                'id' => $member->id,
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'agency' => $member->agency,
                'field_office' => $member->fieldOffice?->name,
                'status' => $member->status->value,
                'status_label' => $member->status->label(),
                'status_variant' => $member->status->badgeVariant(),
            ] : null;
            $notFound = $member === null;

            if ($member && $examinationId) {
                $attendance = $this->confirmExamAttendance($member, $examinationId, $venueId, $user);
            } elseif ($member && $trainingId) {
                $attendance = $this->confirmTrainingAttendance($member, $trainingId, $user);
            }
        }

        if ($examinationId) {
            $venues = ExaminationSchool::where('examination_id', $examinationId)
                ->with('school:id,name')
                ->get()
                ->map(fn (ExaminationSchool $venue) => ['value' => $venue->id, 'label' => $venue->school?->name]);
        }

        return Inertia::render('Scanner/Index', [
            'code' => $raw['code'],
            'examinationId' => $examinationId,
            'trainingId' => $trainingId,
            'examinationSchoolId' => $venueId,
            'result' => $result,
            'nepResult' => $nepResult,
            'notFound' => $notFound,
            'attendance' => $attendance,
            'venues' => $venues,
            'events' => $this->eventOptions(),
            'attendanceSummary' => $this->attendanceSummary($examinationId, $trainingId, $venueId, $user),
        ]);
    }

    /**
     * Bulk manual attendance fallback — mirrors legacy's searchable multi-select
     * modal for members (and, at a selected venue, non-exam personnel) whose QR
     * won't scan. Silently skips anyone already marked present rather than
     * failing the whole batch.
     */
    public function bulkMarkAttendance(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'in:training,exam'],
            'training_id' => ['required_if:type,training', 'nullable', 'integer', 'exists:trainings,id'],
            'examination_id' => ['required_if:type,exam', 'nullable', 'integer', 'exists:examinations,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'nep_assignment_ids' => ['nullable', 'array'],
            'nep_assignment_ids.*' => ['integer', 'exists:nep_assignments,id'],
            'covered_attendance_ids' => ['nullable', 'array'],
            'covered_attendance_ids.*' => ['string', 'regex:/^\d+:\d+$/'],
        ]);

        $memberIds = $validated['member_ids'] ?? [];
        $nepAssignmentIds = $validated['nep_assignment_ids'] ?? [];
        $coveredAttendanceIds = $validated['covered_attendance_ids'] ?? [];

        abort_if(! $memberIds && ! $nepAssignmentIds && ! $coveredAttendanceIds, 422, 'Select at least one person to mark present.');

        $markedCount = 0;

        if ($memberIds) {
            if ($validated['type'] === 'training') {
                $query = TrainingAssignment::where('training_id', $validated['training_id']);
                $certificateType = CertificateType::Appearance;
            } else {
                $query = ExamAssignment::where('examination_id', $validated['examination_id']);
                $certificateType = CertificateType::Appreciation;
            }

            $assignments = $query
                ->whereIn('member_id', $memberIds)
                ->whereNull('attendance_confirmed_at')
                ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->update(['attendance_confirmed_at' => now(), 'attendance_confirmed_by' => $user->id]);
                $this->certificates->generatePending($certificateType, $assignment, $user);
            }

            $markedCount += $assignments->count();
        }

        if ($nepAssignmentIds) {
            $nepAssignments = NepAssignment::whereIn('id', $nepAssignmentIds)->with('personnel')->get();

            foreach ($nepAssignments as $assignment) {
                Gate::authorize('update', $assignment);

                $exists = NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
                    ->where('examination_school_id', $assignment->examination_school_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                NepAttendance::create([
                    'non_exam_personnel_id' => $assignment->non_exam_personnel_id,
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
                    ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                    ->first();

                if (! $assignment) {
                    continue;
                }

                Gate::authorize('update', $assignment);

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

        return back()->with('success', "Marked {$markedCount} person(s) present.");
    }

    /**
     * Live attendance summary (Total / Present / Absent + recent scans +
     * not-yet-present roster for the manual fallback) for the selected
     * training or examination context — mirrors legacy's stats strip and
     * "Recent Attendance" table on the training QR scanner page. In exam
     * mode with a venue selected, non-exam personnel assigned to that venue
     * are folded into the same summary/roster (their attendance is tracked
     * per venue, not per whole examination — see NepAssignmentController).
     */
    private function attendanceSummary(?int $examinationId, ?int $trainingId, ?int $venueId, User $user): ?array
    {
        if ($trainingId) {
            $assignments = TrainingAssignment::with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
                ->where('training_id', $trainingId)
                ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
        } elseif ($examinationId) {
            $assignments = ExamAssignment::with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
                ->where('examination_id', $examinationId)
                ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
        } else {
            return null;
        }

        [$present, $absent] = $assignments->partition(fn ($a) => $a->attendance_confirmed_at !== null);

        $recent = $present->map(fn ($a) => [
            'id' => "member:{$a->id}",
            'name' => $a->member?->name,
            'code' => $a->member?->proctad_id,
            'confirmed_at_raw' => $a->attendance_confirmed_at,
        ]);
        $roster = $absent->values()
            ->map(fn ($a) => [
                'value' => "member:{$a->member_id}",
                'label' => "{$a->member?->name} ({$a->member?->proctad_id})",
            ]);

        $total = $assignments->count();
        $presentCount = $present->count();
        $absentCount = $absent->count();

        if ($examinationId && $venueId) {
            $nepAssignments = NepAssignment::where('examination_school_id', $venueId)
                ->with('personnel:id,nep_id,first_name,middle_name,last_name,suffix')
                ->get();
            $nepAttendance = NepAttendance::where('examination_school_id', $venueId)
                ->get()
                ->keyBy('non_exam_personnel_id');

            [$nepPresent, $nepAbsent] = $nepAssignments->partition(
                fn (NepAssignment $a) => $nepAttendance->has($a->non_exam_personnel_id),
            );

            $total += $nepAssignments->count();
            $presentCount += $nepPresent->count();
            $absentCount += $nepAbsent->count();

            $recent = $recent->concat($nepPresent->map(fn (NepAssignment $a) => [
                'id' => "nep:{$a->id}",
                'name' => $a->personnel?->name,
                'code' => $a->personnel?->nep_id,
                'confirmed_at_raw' => $nepAttendance->get($a->non_exam_personnel_id)->scanned_at,
            ]));

            $roster = $roster
                ->concat($nepAbsent->values()->map(fn (NepAssignment $a) => [
                    'value' => "nep:{$a->id}",
                    'label' => "{$a->personnel?->name} ({$a->personnel?->nep_id}) · Non-Exam Personnel",
                ]));

            // REC/LEC/CE-for-Investigation assignments that list this venue as a
            // covered school (reference-only monitoring, not their testing
            // center) — tracked via exam_assignment_attendances, independent of
            // the assignment's own attendance_confirmed_at.
            $coverageAssignments = ExamAssignment::where('examination_id', $examinationId)
                ->whereHas('coveredSchools', fn ($q) => $q->where('examination_school.id', $venueId))
                ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
                ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->get();
            $coverageAttendance = ExamAssignmentAttendance::where('examination_school_id', $venueId)
                ->whereIn('exam_assignment_id', $coverageAssignments->pluck('id'))
                ->get()
                ->keyBy('exam_assignment_id');

            [$coveragePresent, $coverageAbsent] = $coverageAssignments->partition(
                fn (ExamAssignment $a) => $coverageAttendance->has($a->id),
            );

            $total += $coverageAssignments->count();
            $presentCount += $coveragePresent->count();
            $absentCount += $coverageAbsent->count();

            $recent = $recent->concat($coveragePresent->map(fn (ExamAssignment $a) => [
                'id' => "covered:{$a->id}:{$venueId}",
                'name' => $a->member?->name,
                'code' => $a->member?->proctad_id,
                'confirmed_at_raw' => $coverageAttendance->get($a->id)->scanned_at,
            ]));

            $roster = $roster
                ->concat($coverageAbsent->values()->map(fn (ExamAssignment $a) => [
                    'value' => "covered:{$a->id}:{$venueId}",
                    'label' => "{$a->member?->name} ({$a->member?->proctad_id}) · Covered School ({$a->role->label()})",
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
            'absent' => $absentCount,
            'recent' => $recent,
            'roster' => $roster,
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
            ->first();

        if (! $assignment) {
            return ['outcome' => 'not_assigned'];
        }

        if ($assignment->isCoverageRole() && $venueId && $venueId !== $assignment->examination_school_id) {
            return $this->confirmCoveredSchoolAttendance($assignment, $venueId, $user);
        }

        if ($assignment->isCoverageRole() && ! $venueId && ! $assignment->attendance_confirmed_at) {
            return ['outcome' => 'venue_required'];
        }

        if ($assignment->attendance_confirmed_at) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
                'role_label' => $assignment->role->label(),
            ];
        }

        $assignment->update([
            'attendance_confirmed_at' => now(),
            'attendance_confirmed_by' => $user->id,
        ]);

        $certificate = $this->certificates
            ->generatePending(CertificateType::Appreciation, $assignment, $user);

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
            'role_label' => $assignment->role->label(),
            'certificate' => $certificate->wasRecentlyCreated
                ? $certificate->type->label().' queued for Management approval'
                : null,
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

        $existing = ExamAssignmentAttendance::where('exam_assignment_id', $assignment->id)
            ->where('examination_school_id', $venueId)
            ->first();

        if ($existing) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $existing->scanned_at->format('M d, Y H:i'),
                'role_label' => $assignment->role->label(),
            ];
        }

        $attendance = ExamAssignmentAttendance::create([
            'exam_assignment_id' => $assignment->id,
            'examination_school_id' => $venueId,
            'status' => 'present',
            'scan_method' => 'qr',
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
            'role_label' => $assignment->role->label().' — covered school',
        ];
    }

    /**
     * Training attendance via QR. A confirmed scan auto-queues the Certificate
     * of Appearance for Field Director approval (user-confirmed flow).
     */
    private function confirmTrainingAttendance(Member $member, int $trainingId, User $user): array
    {
        $assignment = TrainingAssignment::where('training_id', $trainingId)
            ->where('member_id', $member->id)
            ->first();

        if (! $assignment) {
            return ['outcome' => 'not_assigned'];
        }

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

        $certificate = $this->certificates
            ->generatePending(CertificateType::Appearance, $assignment, $user);

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $assignment->attendance_confirmed_at->format('M d, Y H:i'),
            'certificate' => $certificate->wasRecentlyCreated
                ? $certificate->type->label().' queued for Field Director approval'
                : null,
        ];
    }

    /**
     * Non-exam personnel attendance at a specific venue (NEP attendance is
     * tracked per examination_school, not per examination — see NepAssignmentController).
     */
    private function confirmNepAttendance(NonExamPersonnel $nep, int $venueId, User $user): array
    {
        $assignment = NepAssignment::where('examination_school_id', $venueId)
            ->where('non_exam_personnel_id', $nep->id)
            ->first();

        if (! $assignment) {
            return ['outcome' => 'not_assigned'];
        }

        $existing = NepAttendance::where('examination_school_id', $venueId)
            ->where('non_exam_personnel_id', $nep->id)
            ->first();

        if ($existing) {
            return [
                'outcome' => 'already_confirmed',
                'confirmed_at' => $existing->scanned_at->format('M d, Y H:i'),
            ];
        }

        $attendance = NepAttendance::create([
            'non_exam_personnel_id' => $nep->id,
            'examination_school_id' => $venueId,
            'status' => 'present',
            'scan_method' => 'qr',
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        return [
            'outcome' => 'confirmed',
            'confirmed_at' => $attendance->scanned_at->format('M d, Y H:i'),
        ];
    }

    /**
     * Grouped selector options: examinations and trainings.
     */
    private function eventOptions(): array
    {
        return [
            'examinations' => Examination::orderByDesc('exam_date')->limit(10)
                ->get(['id', 'title', 'exam_date'])
                ->map(fn (Examination $exam) => [
                    'value' => $exam->id,
                    'label' => "{$exam->title} — {$exam->exam_date->format('M d, Y')}",
                ]),
            'trainings' => Training::whereNull('completed_at')->orderByDesc('training_date')->limit(10)
                ->get(['id', 'title', 'training_date'])
                ->map(fn (Training $training) => [
                    'value' => $training->id,
                    'label' => "{$training->title} — {$training->training_date->format('M d, Y')}",
                ]),
        ];
    }

    /**
     * Accept the raw PROCTAD ID, the full verification URL, an "NEP:{id}" QR
     * payload, or the inconsistent legacy formats still on printed QR stock
     * (e.g. "7|attendance", "PROCTAD-2026-XXXXX|attendance").
     *
     * @return array{type: 'member'|'nep', code: string}
     */
    private function normalize(string $code): array
    {
        $code = trim($code);

        if (Str::contains($code, '/verify/')) {
            return ['type' => 'member', 'code' => strtoupper(trim(Str::afterLast($code, '/verify/'), " /\t\n\r"))];
        }

        if (Str::startsWith(strtoupper($code), 'NEP:')) {
            return ['type' => 'nep', 'code' => strtoupper(trim(Str::after($code, ':')))];
        }

        // Legacy scanners appended "|attendance" (and similar suffixes) to the
        // raw code — strip anything from the first pipe onward.
        $code = Str::before($code, '|');
        $code = strtoupper(trim($code, " /\t\n\r"));

        return Str::startsWith($code, 'NEP-')
            ? ['type' => 'nep', 'code' => $code]
            : ['type' => 'member', 'code' => $code];
    }
}
