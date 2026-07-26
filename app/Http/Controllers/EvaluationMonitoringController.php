<?php

namespace App\Http\Controllers;

use App\Enums\ExamRole;
use App\Models\Evaluation;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Evaluation::class);

        $user = $request->user();
        $fieldOfficeScoped = $user->role->isFieldOfficeScoped();

        $examinationId = $request->integer('examination_id') ?: Examination::query()->orderByDesc('exam_date')->value('id');
        $fieldOfficeId = $fieldOfficeScoped ? $user->field_office_id : ($request->integer('field_office_id') ?: null);

        $assignments = ExamAssignment::query()
            ->with(['member:id,proctad_id,first_name,middle_name,last_name,suffix', 'fieldOffice:id,name,code', 'examinationSchool.school:id,name', 'room:id,room_number'])
            ->withExists('evaluation as has_submitted')
            ->evaluable()
            ->when($examinationId, fn ($q) => $q->where('examination_id', $examinationId), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $request->string('status')->value() === 'submitted'
                    ? $q->whereHas('evaluation')
                    : $q->whereDoesntHave('evaluation');
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->whereHas('member', fn ($qq) => $qq
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('proctad_id', 'like', "%{$term}%"));
            })
            ->orderBy('field_office_id')
            // Seniority order, not alphabetical. Expressed as CASE rather than
            // MySQL's FIELD(): FIELD does not exist in SQLite, so this page threw
            // a 500 under test the moment there was a row to sort — which is why
            // it had no coverage, and why an inconsistency here went unnoticed.
            // Built from evaluableCases() so the ordering cannot fall out of step
            // with the set of roles being listed.
            ->orderByRaw($this->roleSeniorityOrdering())
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ExamAssignment $assignment) => [
                'id' => $assignment->id,
                'member' => [
                    'id' => $assignment->member->id,
                    'name' => $assignment->member->name,
                    'proctad_id' => $assignment->member->proctad_id,
                ],
                'field_office' => $assignment->fieldOffice?->only('id', 'name', 'code'),
                'role' => $assignment->role->value,
                'role_label' => $assignment->role->is(ExamRole::Proctor) ? 'Room Proctor' : $assignment->role->label(),
                'venue' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room?->room_number,
                'has_submitted' => (bool) $assignment->has_submitted,
                'evaluation_id' => $assignment->evaluation?->id,
            ]);

        return Inertia::render('EvaluationMonitoring/Index', [
            'assignments' => $assignments,
            'filters' => $request->only('examination_id', 'field_office_id', 'role', 'status', 'search'),
            'examinations' => Examination::query()->orderByDesc('exam_date')->limit(100)->get(['id', 'title', 'exam_date'])
                ->map(fn (Examination $exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'exam_date' => $exam->exam_date->format('F j, Y'),
                ]),
            'fieldOffices' => $fieldOfficeScoped ? null : FieldOffice::query()->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => collect(ExamRole::evaluableCases())->map(fn (ExamRole $role) => [
                'value' => $role->value,
                'label' => $role === ExamRole::Proctor ? 'Room Proctor' : $role->label(),
            ]),
            'summary' => $this->summary($examinationId, $fieldOfficeId),
        ]);
    }

    public function show(Evaluation $evaluation): Response
    {
        Gate::authorize('view', $evaluation);

        $evaluation->load(['fieldOffice:id,name,code', 'school:id,name', 'examination:id,title,exam_date', 'assignment.member:id,proctad_id,first_name,middle_name,last_name,suffix']);
        $evaluation->setAttribute('submitted_at', $evaluation->created_at->format('F j, Y g:i A'));

        return Inertia::render('EvaluationMonitoring/Show', [
            'evaluation' => $evaluation,
            'criteria' => \App\Support\EvaluationCriteria::toArray(),
        ]);
    }

    /**
     * Portable equivalent of MySQL's FIELD(): a CASE ranking roles in the order
     * evaluableCases() declares them.
     *
     * The values are enum-backed, never user input, so interpolation here cannot
     * carry anything a caller controls.
     */
    private function roleSeniorityOrdering(): string
    {
        $cases = '';

        foreach (ExamRole::evaluableCases() as $index => $role) {
            $cases .= " WHEN '{$role->value}' THEN {$index}";
        }

        return "CASE role{$cases} ELSE ".count(ExamRole::evaluableCases()).' END';
    }

    private function summary(?int $examinationId, ?int $fieldOfficeId): array
    {
        $base = ExamAssignment::query()
            ->evaluable()
            ->when($examinationId, fn ($q) => $q->where('examination_id', $examinationId), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

        $total = (clone $base)->count();
        $submitted = (clone $base)->whereHas('evaluation')->count();

        return [
            'total' => $total,
            'submitted' => $submitted,
            'not_submitted' => $total - $submitted,
            'percentage' => $total > 0 ? round($submitted / $total * 100, 1) : 0.0,
        ];
    }
}
