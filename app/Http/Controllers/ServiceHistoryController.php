<?php

namespace App\Http\Controllers;

use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Services\TestAdministratorServiceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceHistoryController extends Controller
{
    /** Exam-day designations tracked as "Test Administrator" service, per the roster this page reports on. */
    private const TA_ROLES = TestAdministratorServiceHistory::ROLES;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);

        $user = $request->user();
        $fieldOfficeScoped = $user->role->isFieldOfficeScoped();
        $fieldOfficeId = $fieldOfficeScoped ? $user->field_office_id : ($request->integer('field_office_id') ?: null);
        $taRoleValues = array_column(self::TA_ROLES, 'value');

        $members = Member::query()
            ->with('fieldOffice:id,name,code')
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
            ->whereHas('assignments', fn ($q) => $q
                ->whereIn('role', $taRoleValues)
                ->whereNotNull('attendance_confirmed_at'))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('assignments', fn ($qq) => $qq
                ->where('role', $request->string('role'))
                ->whereNotNull('attendance_confirmed_at')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($qq) => $qq
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('proctad_id', 'like', "%{$term}%"));
            })
            ->with(['assignments' => fn ($q) => $q
                ->whereIn('role', $taRoleValues)
                ->whereNotNull('attendance_confirmed_at')
                ->with('examination:id,title,exam_date')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Member $member) {
                $history = $member->assignments->sortByDesc(fn ($a) => $a->examination?->exam_date)->values();
                $latest = $history->first();
                $byRole = $history->countBy(fn ($a) => $a->role->value);

                return [
                    'id' => $member->id,
                    'proctad_id' => $member->proctad_id,
                    'name' => $member->name,
                    'field_office' => $member->fieldOffice?->only('id', 'name', 'code'),
                    'total_served' => $history->count(),
                    'designations' => collect(self::TA_ROLES)
                        ->map(fn (ExamRole $role) => [
                            'role' => $role->value,
                            'label' => $role->label(),
                            'count' => $byRole->get($role->value, 0),
                        ])
                        ->filter(fn ($d) => $d['count'] > 0)
                        ->values(),
                    'latest' => $latest ? [
                        'exam_title' => $latest->examination?->title,
                        'exam_date' => $latest->examination?->exam_date?->format('M d, Y'),
                        'role_label' => $latest->role->label(),
                    ] : null,
                ];
            });

        return Inertia::render('ServiceHistory/Index', [
            'members' => $members,
            'filters' => $request->only('field_office_id', 'role', 'search'),
            'fieldOffices' => $fieldOfficeScoped ? null : FieldOffice::query()->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => collect(self::TA_ROLES)->map(fn (ExamRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
            'summary' => $this->summary($fieldOfficeId, $taRoleValues),
        ]);
    }

    public function show(Request $request, Member $member): JsonResponse
    {
        Gate::authorize('view', $member);

        $member->load('fieldOffice:id,name,code');
        $history = TestAdministratorServiceHistory::forMember($member);

        return response()->json([
            'member' => [
                'id' => $member->id,
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'field_office' => $member->fieldOffice?->only('id', 'name', 'code'),
            ],
            'summary' => [
                'total_served' => $history['total_served'],
                'designations' => $history['designations'],
            ],
            'records' => $history['records'],
        ]);
    }

    private function summary(?int $fieldOfficeId, array $taRoleValues): array
    {
        $base = ExamAssignment::query()
            ->whereIn('role', $taRoleValues)
            ->whereNotNull('attendance_confirmed_at')
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

        $totalAdministrators = (clone $base)->distinct()->count('member_id');
        $totalServed = (clone $base)->count();
        $byRole = (clone $base)->selectRaw('role, count(*) as cnt')->groupBy('role')->pluck('cnt', 'role');

        $mostRecent = (clone $base)
            ->join('examinations', 'examinations.id', '=', 'exam_assignments.examination_id')
            ->orderByDesc('examinations.exam_date')
            ->select('examinations.title', 'examinations.exam_date')
            ->first();

        return [
            'total_administrators' => $totalAdministrators,
            'total_served' => $totalServed,
            'by_designation' => collect(self::TA_ROLES)->map(fn (ExamRole $role) => [
                'label' => $role->label(),
                'count' => $byRole->get($role->value, 0),
            ]),
            'most_recent' => $mostRecent ? [
                'title' => $mostRecent->title,
                'date' => Carbon::parse($mostRecent->exam_date)->format('M d, Y'),
            ] : null,
        ];
    }
}
