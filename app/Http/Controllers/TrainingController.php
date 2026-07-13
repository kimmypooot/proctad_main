<?php

namespace App\Http\Controllers;

use App\Enums\BlacklistStatus;
use App\Enums\CertificateType;
use App\Enums\TrainingType;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Services\CertificateService;
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

        return Inertia::render('Trainings/Index', [
            'trainings' => Training::withCount('assignments')
                ->orderByDesc('training_date')
                ->get()
                ->map(fn (Training $training) => [
                    ...$training->only('id', 'title', 'venue', 'assignments_count'),
                    'type' => $training->type->value,
                    'type_label' => $training->type->shortLabel(),
                    'training_date' => $training->training_date->toDateString(),
                    'completed' => $training->completed_at !== null,
                ]),
            'types' => collect(TrainingType::cases())
                ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])->all(),
            'can' => ['manage' => $request->user()->can('create', Training::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Training::class);

        Training::create($this->validated($request));

        return back()->with('success', 'Training created.');
    }

    public function show(Request $request, Training $training): Response
    {
        Gate::authorize('view', $training);

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

        return Inertia::render('Trainings/Show', [
            'training' => [
                ...$training->only('id', 'title', 'venue'),
                'type_label' => $training->type->label(),
                'training_date' => $training->training_date->toDateString(),
                'completed' => $training->completed_at !== null,
                'completed_at' => $training->completed_at?->format('M d, Y'),
            ],
            'assignments' => $assignments,
            'assignableMembers' => $assignable,
            'can' => [
                'assign' => $user->can('create', TrainingAssignment::class),
                'complete' => $user->can('complete', $training) && $training->completed_at === null,
            ],
        ]);
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
     * Conclude the training: Certificates of Completion are auto-issued and
     * released to every attendance-confirmed participant (spec 3.2 — no
     * approval step applies to Completion certificates).
     */
    public function complete(Request $request, Training $training, CertificateService $certificates): RedirectResponse
    {
        Gate::authorize('complete', $training);

        abort_if($training->completed_at !== null, 400, 'Training is already completed.');

        $training->update(['completed_at' => now()]);

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

        return back()->with('success', "Training marked as completed. {$issued} Certificate(s) of Completion issued and emailed.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TrainingType::class)],
            'training_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:training_date'],
            'venue' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
