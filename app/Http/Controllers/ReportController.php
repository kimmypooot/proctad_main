<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Exports\MembersExport;
use App\Exports\ServiceRecordsExport;
use App\Exports\TrainingAttendanceExport;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TrainingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Region-wide / per-Field-Office statistics (spec §5 "Statistical dashboards",
 * §4.3 "Generate reports/statistics by FO, year, exam, gender"). Field-office
 * scoped roles (fo_admin, field_director) are locked to their own FO; region-
 * wide roles may filter by any FO or leave it blank for a region-wide view.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        ['fieldOfficeId' => $fieldOfficeId, 'year' => $year, 'examTypeId' => $examTypeId, 'sex' => $sex] = $this->resolveFilters($request);
        $user = $request->user();

        $assignments = ExamAssignment::query()
            ->with(['examination:id,title,exam_type_id,exam_date', 'member:id,sex,field_office_id', 'fieldOffice:id,name,code'])
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
            ->when($year || $examTypeId, fn ($q) => $q->whereHas('examination', function ($qq) use ($year, $examTypeId) {
                $qq->when($year, fn ($q3) => $q3->whereYear('exam_date', $year))
                    ->when($examTypeId, fn ($q3) => $q3->where('exam_type_id', $examTypeId));
            }))
            ->when($sex, fn ($q) => $q->whereHas('member', fn ($qq) => $qq->where('sex', $sex)))
            ->get();

        return Inertia::render('Reports/Index', [
            'filters' => [
                'field_office_id' => $fieldOfficeId,
                'year' => $year,
                'exam_type_id' => $examTypeId,
                'sex' => $sex,
            ],
            'filterOptions' => [
                'fieldOffices' => $user->role->isFieldOfficeScoped() ? [] : FieldOffice::orderBy('name')->get(['id', 'name', 'code']),
                'examTypes' => ExamType::orderBy('name')->get(['id', 'name']),
                'years' => $this->availableYears(),
                'canPickFieldOffice' => ! $user->role->isFieldOfficeScoped(),
            ],
            'summary' => [
                'active_members' => Member::where('status', MemberStatus::Active)
                    ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
                    ->when($sex, fn ($q) => $q->where('sex', $sex))
                    ->count(),
                'service_records' => $assignments->count(),
                'attended' => $assignments->whereNotNull('attendance_confirmed_at')->count(),
            ],
            'byFieldOffice' => $this->byFieldOffice($assignments),
            'byGender' => $this->byGender($assignments),
            'byExamType' => $this->byExamType($assignments),
            'byYear' => $this->byYear($assignments),
            'trainingStats' => $this->trainingStats($fieldOfficeId, $year),
            'venueReadiness' => $this->venueReadiness($fieldOfficeId, $year, $examTypeId),
        ]);
    }

    public function exportMembers(Request $request): BinaryFileResponse
    {
        $filters = $this->resolveFilters($request);
        $status = $request->string('status')->value() ?: null;

        return Excel::download(
            new MembersExport($filters['fieldOfficeId'], $status),
            'proctad-members-'.now()->format('Ymd').'.xlsx',
        );
    }

    public function exportServiceRecords(Request $request): BinaryFileResponse
    {
        $filters = $this->resolveFilters($request);

        return Excel::download(
            new ServiceRecordsExport($filters['fieldOfficeId'], $filters['year'], $filters['examTypeId']),
            'proctad-service-records-'.now()->format('Ymd').'.xlsx',
        );
    }

    public function exportTrainingAttendance(Request $request): BinaryFileResponse
    {
        $filters = $this->resolveFilters($request);
        $trainingId = $request->integer('training_id') ?: null;

        return Excel::download(
            new TrainingAttendanceExport($filters['fieldOfficeId'], $filters['year'], $trainingId),
            'proctad-training-attendance-'.now()->format('Ymd').'.xlsx',
        );
    }

    /**
     * @return array{fieldOfficeId: ?int, year: ?int, examTypeId: ?int, sex: ?string}
     */
    private function resolveFilters(Request $request): array
    {
        abort_if($request->user()->role === UserRole::Member, 403);

        $user = $request->user();

        return [
            'fieldOfficeId' => $user->role->isFieldOfficeScoped()
                ? $user->field_office_id
                : ($request->integer('field_office_id') ?: null),
            'year' => $request->integer('year') ?: null,
            'examTypeId' => $request->integer('exam_type_id') ?: null,
            'sex' => $request->string('sex')->value() ?: null,
        ];
    }

    private function byFieldOffice(Collection $assignments): array
    {
        return $assignments->groupBy('field_office_id')
            ->map(fn (Collection $group, $foId) => [
                'label' => $group->first()->fieldOffice?->name ?? 'Unassigned',
                'total' => $group->count(),
                'attended' => $group->whereNotNull('attendance_confirmed_at')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function byGender(Collection $assignments): array
    {
        return $assignments->groupBy(fn ($a) => $a->member?->sex ?? 'unknown')
            ->map(fn (Collection $group, $sex) => [
                'label' => ucfirst($sex),
                'total' => $group->count(),
                'attended' => $group->whereNotNull('attendance_confirmed_at')->count(),
            ])
            ->values()
            ->all();
    }

    private function byExamType(Collection $assignments): array
    {
        return $assignments->groupBy(fn ($a) => $a->examination?->exam_type_id ?? 'none')
            ->map(fn (Collection $group) => [
                'label' => $group->first()->examination?->title ?? 'Untitled',
                'total' => $group->count(),
                'attended' => $group->whereNotNull('attendance_confirmed_at')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function byYear(Collection $assignments): array
    {
        return $assignments->groupBy(fn ($a) => $a->examination?->exam_date?->year ?? 'unknown')
            ->map(fn (Collection $group, $year) => [
                'label' => (string) $year,
                'total' => $group->count(),
                'attended' => $group->whereNotNull('attendance_confirmed_at')->count(),
            ])
            ->sortByDesc('label')
            ->values()
            ->all();
    }

    private function trainingStats(?int $fieldOfficeId, ?int $year): array
    {
        $trainingAssignments = TrainingAssignment::query()
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
            ->when($year, fn ($q) => $q->whereHas('training', fn ($qq) => $qq->whereYear('training_date', $year)))
            ->get();

        $total = $trainingAssignments->count();
        $attended = $trainingAssignments->whereNotNull('attendance_confirmed_at')->count();

        return [
            'total_participants' => $total,
            'attended' => $attended,
            'attendance_rate' => $total > 0 ? round($attended / $total * 100, 1) : null,
        ];
    }

    /**
     * Per-venue staffing readiness: rooms with at least one linked assignment
     * vs total rooms. Not a legacy concept — designed here since the checkpoint
     * calls for a readiness view and per-room staffing is already modeled.
     */
    private function venueReadiness(?int $fieldOfficeId, ?int $year, ?int $examTypeId): array
    {
        return ExaminationSchool::query()
            ->with(['school:id,name,field_office_id', 'examination:id,title,exam_date,exam_type_id', 'rooms:id,examination_school_id'])
            ->when($fieldOfficeId, fn ($q) => $q->whereHas('school', fn ($qq) => $qq->where('field_office_id', $fieldOfficeId)))
            ->when($year || $examTypeId, fn ($q) => $q->whereHas('examination', function ($qq) use ($year, $examTypeId) {
                $qq->when($year, fn ($q3) => $q3->whereYear('exam_date', $year))
                    ->when($examTypeId, fn ($q3) => $q3->where('exam_type_id', $examTypeId));
            }))
            ->get()
            ->map(function (ExaminationSchool $venue) {
                $totalRooms = $venue->rooms->count();
                $staffedRooms = $totalRooms === 0 ? 0 : ExamAssignment::where('examination_school_id', $venue->id)
                    ->whereNotNull('exam_room_id')
                    ->distinct('exam_room_id')
                    ->count('exam_room_id');

                return [
                    'school' => $venue->school?->name ?? 'Unknown',
                    'examination' => $venue->examination?->title ?? 'Unknown',
                    'exam_date' => $venue->examination?->exam_date?->format('M d, Y'),
                    'total_rooms' => $totalRooms,
                    'staffed_rooms' => $staffedRooms,
                    'readiness_percent' => $totalRooms > 0 ? round($staffedRooms / $totalRooms * 100) : null,
                ];
            })
            ->sortBy('readiness_percent')
            ->values()
            ->all();
    }

    private function availableYears(): array
    {
        return Examination::pluck('exam_date')
            ->map(fn ($date) => $date->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
