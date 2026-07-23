<?php

namespace App\Http\Controllers;

use App\Enums\BlacklistStatus;
use App\Enums\CertificateType;
use App\Enums\TrainingType;
use App\Models\Examination;
use App\Models\Member;
use App\Models\ScannerSession;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Training::class);

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        return Inertia::render('Trainings/Index', [
            'trainings' => Training::withCount('assignments')
                ->with('fieldOffice:id,name', 'exam:id,title,exam_date')
                ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->orderByDesc('training_date')
                ->get()
                ->map(fn (Training $training) => [
                    ...$training->only('id', 'title', 'venue', 'assignments_count'),
                    'type' => $training->type->value,
                    'type_label' => $training->type->shortLabel(),
                    'training_date' => $training->training_date->toDateString(),
                    'completed' => $training->completed_at !== null,
                    'field_office' => $training->relationLoaded('fieldOffice') ? $training->fieldOffice?->only('id', 'name') : null,
                    'exam' => $training->relationLoaded('exam') && $training->exam
                        ? $training->exam->only('id', 'title', 'exam_date')
                        : null,
                ]),
            'types' => collect(TrainingType::cases())
                ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])->all(),
            'exams' => Examination::where('is_active', true)
                ->orderByDesc('exam_date')
                ->get(['id', 'title', 'exam_date'])
                ->map(fn (Examination $exam) => [
                    'value' => $exam->id,
                    'label' => "{$exam->title} — {$exam->exam_date->format('F j, Y')}",
                ]),
            'can' => ['manage' => $user->can('create', Training::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Training::class);

        $data = $this->validated($request);

        if ($request->user()->role->isFieldOfficeScoped()) {
            $data['field_office_id'] = $request->user()->field_office_id;
        }

        Training::create($data);

        return back()->with('success', 'Training created.');
    }

    public function modal(Request $request, Training $training): JsonResponse
    {
        Gate::authorize('view', $training);

        $training->load('fieldOffice:id,name', 'exam:id,title,exam_date');

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        $assignments = $training->assignments()
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix', 'fieldOffice:id,name,code')
            ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->get()
            ->map(fn (TrainingAssignment $assignment) => [
                'id' => $assignment->id,
                'member' => [
                    'id' => $assignment->member->id,
                    'proctad_id' => $assignment->member->proctad_id,
                    'name' => $assignment->member->name,
                ],
                'field_office' => $assignment->fieldOffice?->only('id', 'name', 'code'),
                'attended' => (bool) $assignment->attendance_confirmed_at,
                'attendance_confirmed_at' => $assignment->attendance_confirmed_at?->format('M d, Y H:i'),
                'can_manage' => $user->can('update', $assignment),
            ]);

        $assignable = $user->can('create', TrainingAssignment::class)
            ? Member::query()
                ->where('status', 'active')
                ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->whereDoesntHave('trainingAssignments', fn ($q) => $q->where('training_id', $training->id))
                ->whereDoesntHave('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))
                ->orderBy('last_name')
                ->get(['id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix'])
                ->map(fn (Member $member) => [
                    'id' => $member->id,
                    'label' => "{$member->name} ({$member->proctad_id})",
                ])
            : [];

        return response()->json([
            'training' => [
                'id' => $training->id,
                'title' => $training->title,
                'type_label' => $training->type->label(),
                // Drives the "Mark completed" confirmation copy — only a TEA
                // carries a Certificate of Completion.
                'issues_completion' => $training->type->issuesCompletionCertificate(),
                'training_date' => $training->training_date->toDateString(),
                'end_date' => $training->end_date?->toDateString(),
                'venue' => $training->venue,
                'completed' => $training->completed_at !== null,
                'completed_at' => $training->completed_at?->format('M d, Y'),
                'field_office_id' => $training->field_office_id,
                'field_office' => $training->relationLoaded('fieldOffice') ? $training->fieldOffice?->only('id', 'name') : null,
                'exam' => $training->relationLoaded('exam') && $training->exam
                    ? $training->exam->only('id', 'title', 'exam_date')
                    : null,
            ],
            'assignments' => $assignments,
            'assignableMembers' => $assignable,
            'scannerSessions' => ScannerSessionController::panelData('training_id', $training->id),
            'can' => [
                'assign' => $user->can('create', TrainingAssignment::class),
                'manage' => $user->can('update', $training),
                'complete' => $user->can('complete', $training) && $training->completed_at === null,
                'manageScannerLinks' => $user->can('create', ScannerSession::class),
            ],
        ]);
    }

    public function show(Training $training): RedirectResponse
    {
        Gate::authorize('view', $training);

        return redirect()->route('trainings.index')->with('info', 'Training details are now available from the list.');
    }

    public function update(Request $request, Training $training): RedirectResponse
    {
        Gate::authorize('update', $training);

        $training->update($this->validated($request));

        return back()->with('success', 'Training updated.');
    }

    public function destroy(Training $training): RedirectResponse
    {
        Gate::authorize('delete', $training);

        $training->delete();

        return redirect()->route('trainings.index')->with('success', 'Training removed.');
    }

    /**
     * Conclude the training.
     *
     * Only a TEA carries a Certificate of Completion — a Briefing is an
     * information session with nothing to complete, and issues Appearance
     * certificates alone (see TrainingType::issuesCompletionCertificate).
     *
     * Attendance scans already issue Completion as they happen, so this is
     * normally a no-op backstop that catches anyone whose attendance was
     * recorded before the training was concluded.
     */
    public function complete(Request $request, Training $training, CertificateService $certificates): RedirectResponse
    {
        Gate::authorize('complete', $training);

        abort_if($training->completed_at !== null, 400, 'Training is already completed.');

        $training->update(['completed_at' => now()]);

        if (! $training->type->issuesCompletionCertificate()) {
            return back()->with('success', 'Training marked as completed.');
        }

        $issued = 0;
        $attended = $training->assignments()->whereNotNull('attendance_confirmed_at')->get();

        foreach ($attended as $assignment) {
            // generatePending() auto-releases Completion certificates itself
            // (no approver exists for this type) — no separate release() call needed.
            $certificate = $certificates->generatePending(CertificateType::Completion, $assignment, $request->user());
            if ($certificate->wasRecentlyCreated) {
                $issued++;
            }
        }

        return back()->with('success', $issued > 0
            ? "Training marked as completed. {$issued} Certificate(s) of Completion issued and emailed."
            : 'Training marked as completed. Every attendee already holds their Certificate of Completion.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TrainingType::class)],
            'training_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:training_date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'exam_id' => ['required', 'integer', 'exists:examinations,id'],
        ]);
    }
}
