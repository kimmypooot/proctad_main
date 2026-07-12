<?php

namespace App\Http\Controllers;

use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Exports\RoomAssignmentsExport;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamType;
use App\Models\Member;
use App\Models\NepAssignment;
use App\Models\NepAttendance;
use App\Models\NonExamPersonnel;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExaminationController extends Controller
{
    private const ROOMS_PER_SUPERVISOR = 5; // matches StaffingRandomizer's default anchoring group size
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Examination::class);

        $examinations = Examination::withCount([
            'assignments',
            'assignments as confirmed_assignments_count' => fn ($q) => $q->where('status', 'confirmed'),
        ])
            ->with('venues.rooms:id,examination_school_id')
            ->orderByDesc('exam_date')
            ->get();

        return Inertia::render('Examinations/Index', [
            'examinations' => $examinations->map(function (Examination $exam) {
                $roomsCount = $exam->venues->sum(fn ($venue) => $venue->rooms->count());

                return [
                    ...$exam->only('id', 'title', 'type', 'exam_type_id', 'assignments_count'),
                    'exam_date' => $exam->exam_date->toDateString(),
                    'upcoming' => $exam->exam_date->isFuture(),
                    'venues_count' => $exam->venues->count(),
                    'rooms_count' => $roomsCount,
                    'confirmed_count' => $exam->confirmed_assignments_count,
                    'staffing_ratio' => $exam->assignments_count > 0
                        ? round(($exam->confirmed_assignments_count / $exam->assignments_count) * 100)
                        : null,
                ];
            }),
            'stats' => [
                'total' => $examinations->count(),
                'upcoming' => $examinations->filter(fn ($exam) => $exam->exam_date->isFuture())->count(),
                'fully_staffed' => $examinations->filter(fn ($exam) => $exam->assignments_count > 0
                    && $exam->confirmed_assignments_count === $exam->assignments_count)->count(),
            ],
            'examTypes' => ExamType::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExamType $type) => ['value' => $type->id, 'label' => $type->name]),
            'can' => ['manage' => $request->user()->can('create', Examination::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Examination::class);

        $examination = Examination::create($this->validated($request));

        // Land directly in the setup wizard (Step 1: Venues & Rooms) instead of
        // staying on the index — the natural next action after creating an exam.
        return redirect()
            ->route('examinations.show', $examination)
            ->with('success', 'Examination created — attach a venue to get started.');
    }

    public function show(Request $request, Examination $examination): Response
    {
        Gate::authorize('view', $examination);

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        $assignments = $examination->assignments()
            ->with(
                'member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'fieldOffice:id,name,code',
                'examinationSchool.school:id,name',
                'room:id,room_number',
                'coveredSchools.school:id,name',
                'attendances',
            )
            ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->get()
            ->map(fn (ExamAssignment $assignment) => [
                'id' => $assignment->id,
                'member' => [
                    'id' => $assignment->member->id,
                    'proctad_id' => $assignment->member->proctad_id,
                    'name' => $assignment->member->name,
                ],
                'field_office' => $assignment->fieldOffice?->only('id', 'name', 'code'),
                'role' => $assignment->role->value,
                'role_label' => $assignment->role->label(),
                'role_group' => $assignment->role->group()->value,
                'role_group_label' => $assignment->role->group()->label(),
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'status_variant' => $assignment->status->badgeVariant(),
                'confirmation_sent_at' => $assignment->confirmation_sent_at?->format('M d, Y H:i'),
                'venue' => $assignment->examinationSchool?->school?->name,
                'room' => $assignment->room?->room_number,
                'examination_school_id' => $assignment->examination_school_id,
                'exam_room_id' => $assignment->exam_room_id,
                'attended' => (bool) $assignment->attendance_confirmed_at,
                'attendance_confirmed_at' => $assignment->attendance_confirmed_at?->format('M d, Y H:i'),
                'rating' => $assignment->performance_rating?->value,
                'rating_label' => $assignment->performance_rating?->label(),
                'rating_variant' => $assignment->performance_rating?->badgeVariant(),
                'remarks' => $assignment->remarks,
                'can_manage' => $user->can('update', $assignment),
                'is_coverage_role' => $assignment->isCoverageRole(),
                'covered_schools' => $assignment->coveredSchools->map(fn ($school) => [
                    'id' => $school->id,
                    'name' => $school->school?->name,
                    'attended' => $assignment->attendances->contains('examination_school_id', $school->id),
                ]),
            ]);

        // Members the current user may assign that aren't on this exam yet.
        $assignable = $user->can('create', ExamAssignment::class)
            ? Member::query()
                ->where('status', 'active')
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->whereDoesntHave('assignments', fn ($q) => $q->where('examination_id', $examination->id))
                ->orderBy('last_name')
                ->get(['id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'field_office_id'])
                ->map(fn (Member $member) => [
                    'id' => $member->id,
                    'label' => "{$member->name} ({$member->proctad_id})",
                ])
            : [];

        $roomRoles = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value, ExamRole::SupervisingExaminer->value];
        $roomsPerSupervisor = 5; // matches StaffingRandomizer's default anchoring group size

        $venues = $examination->venues()
            ->with('school:id,name,municipality,field_office_id', 'rooms', 'nepAssignments.personnel:id,nep_id,first_name,middle_name,last_name,suffix,personnel_type')
            ->when($foScoped, fn ($q) => $q->whereHas('school', fn ($s) => $s->where('field_office_id', $user->field_office_id)))
            ->get()
            ->map(function (ExaminationSchool $venue) use ($assignments, $roomRoles, $roomsPerSupervisor) {
                $roomsCount = $venue->rooms->count();
                $venueAssignments = $assignments->filter(fn ($a) => $a['examination_school_id'] === $venue->id && in_array($a['role'], $roomRoles, true));
                $assignedByRole = $venueAssignments->countBy('role');
                $normalizedAssignments = $venueAssignments->map(fn ($a) => [
                    'id' => $a['id'],
                    'role' => $a['role'],
                    'exam_room_id' => $a['exam_room_id'],
                    'member_name' => $a['member']['name'],
                ]);
                $unassignedPool = collect($roomRoles)->mapWithKeys(fn ($role) => [
                    $role => $normalizedAssignments
                        ->where('role', $role)
                        ->whereNull('exam_room_id')
                        ->map(fn ($a) => ['id' => $a['id'], 'name' => $a['member_name']])
                        ->values(),
                ]);
                $required = [
                    'proctor' => $roomsCount,
                    'room_examiner' => $roomsCount,
                    'supervising_examiner' => (int) ceil($roomsCount / $roomsPerSupervisor),
                ];
                $requiredTotal = array_sum($required);
                $assignedTotal = $venueAssignments->count();

                return [
                    'id' => $venue->id,
                    'school_name' => $venue->school?->name,
                    'municipality' => $venue->school?->municipality,
                    'rooms' => $venue->rooms->map(fn ($room) => [
                        'id' => $room->id,
                        'room_number' => $room->room_number,
                        'capacity' => $room->capacity,
                        'designation' => $room->designation,
                    ]),
                    'rooms_count' => $roomsCount,
                    'total_capacity' => $venue->rooms->sum('capacity'),
                    'room_breakdown' => $this->buildRoomBreakdown($venue->rooms, $normalizedAssignments),
                    'unassigned_pool' => $unassignedPool,
                    'staffing' => [
                        'required' => $required,
                        'assigned' => [
                            'proctor' => $assignedByRole->get('proctor', 0),
                            'room_examiner' => $assignedByRole->get('room_examiner', 0),
                            'supervising_examiner' => $assignedByRole->get('supervising_examiner', 0),
                        ],
                        'required_total' => $requiredTotal,
                        'assigned_total' => $assignedTotal,
                        'ratio' => $requiredTotal > 0 ? min(100, round(($assignedTotal / $requiredTotal) * 100)) : null,
                    ],
                    'nep_assignments' => $venue->nepAssignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'name' => $assignment->personnel?->name,
                        'nep_id' => $assignment->personnel?->nep_id,
                        'personnel_type_label' => $assignment->personnel?->personnel_type?->label(),
                        'role_group' => $assignment->personnel?->personnel_type?->group()->value,
                        'role_group_label' => $assignment->personnel?->personnel_type?->group()->label(),
                        'present' => $assignment->personnel && NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
                            ->where('examination_school_id', $assignment->examination_school_id)
                            ->exists(),
                    ]),
                ];
            });

        $availableNep = $user->can('create', NepAssignment::class)
            ? NonExamPersonnel::query()
                ->where('is_active', true)
                ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'nep_id'])
                ->map(fn (NonExamPersonnel $nep) => ['value' => $nep->id, 'label' => "{$nep->name} ({$nep->nep_id})"])
            : [];

        $availableSchools = $user->can('create', ExaminationSchool::class)
            ? School::query()
                ->where('is_active', true)
                ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->whereDoesntHave('examinationSchools', fn ($q) => $q->where('examination_id', $examination->id))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (School $school) => ['value' => $school->id, 'label' => $school->name])
            : [];

        return Inertia::render('Examinations/Show', [
            'examination' => [
                ...$examination->only('id', 'title', 'type'),
                'exam_date' => $examination->exam_date->toDateString(),
                'upcoming' => $examination->exam_date->isFuture(),
            ],
            'assignments' => $assignments,
            'assignableMembers' => $assignable,
            'venues' => $venues,
            'availableSchools' => $availableSchools,
            'availableNep' => $availableNep,
            'can' => [
                'assign' => $user->can('create', ExamAssignment::class),
                'manageNep' => $user->can('create', NepAssignment::class),
                'manageVenues' => $user->can('create', ExaminationSchool::class),
                'bulkRevoke' => $user->role === UserRole::SuperAdmin,
            ],
            'roles' => collect(ExamRole::cases())
                ->map(fn ($role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'group' => $role->group()->value,
                    'group_label' => $role->group()->label(),
                    'is_coverage' => $role->isCoverageRole(),
                ])->all(),
            'ratings' => collect(PerformanceRating::cases())
                ->map(fn ($rating) => ['value' => $rating->value, 'label' => $rating->label()])->all(),
        ]);
    }

    /** Room-by-room Proctor / Room Examiner / Supervising Examiner breakdown, exported as a formatted spreadsheet. */
    public function exportRoomAssignments(Request $request, Examination $examination): BinaryFileResponse
    {
        Gate::authorize('view', $examination);

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        $validated = $request->validate([
            'venue_id' => ['nullable', 'integer', Rule::exists('examination_school', 'id')->where('examination_id', $examination->id)],
            'status' => ['nullable', 'string', 'in:all,complete,incomplete'],
        ]);
        $status = $validated['status'] ?? 'all';

        $roomRoles = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value, ExamRole::SupervisingExaminer->value];

        $venues = $examination->venues()
            ->with('school:id,name,field_office_id', 'rooms')
            ->when($foScoped, fn ($q) => $q->whereHas('school', fn ($s) => $s->where('field_office_id', $user->field_office_id)))
            ->when(! empty($validated['venue_id']), fn ($q) => $q->where('id', $validated['venue_id']))
            ->get();

        $assignments = $examination->assignments()
            ->whereIn('role', $roomRoles)
            ->whereIn('examination_school_id', $venues->pluck('id'))
            ->with('member:id,first_name,middle_name,last_name,suffix')
            ->get()
            ->map(fn (ExamAssignment $a) => [
                'id' => $a->id,
                'examination_school_id' => $a->examination_school_id,
                'role' => $a->role->value,
                'exam_room_id' => $a->exam_room_id,
                'member_name' => $a->member?->name,
            ]);

        $rows = collect();
        $venueLabel = null;

        foreach ($venues as $venue) {
            $venueAssignments = $assignments->where('examination_school_id', $venue->id);

            foreach ($this->buildRoomBreakdown($venue->rooms, $venueAssignments) as $room) {
                $rows->push([...$room, 'venue_name' => $venue->school?->name]);
            }

            if (! empty($validated['venue_id']) && (int) $validated['venue_id'] === $venue->id) {
                $venueLabel = $venue->school?->name;
            }
        }

        if ($status !== 'all') {
            $rows = $rows->filter(fn ($row) => $row['complete'] === ($status === 'complete'))->values();
        }

        $filename = sprintf('room-assignments-%s-%s.xlsx', Str::slug($examination->title), now()->format('Ymd_His'));

        return Excel::download(new RoomAssignmentsExport($rows, $examination, $venueLabel, $status), $filename);
    }

    public function update(Request $request, Examination $examination): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $examination->update($this->validated($request));

        return back()->with('success', 'Examination updated.');
    }

    public function destroy(Examination $examination): RedirectResponse
    {
        Gate::authorize('delete', $examination);

        $examination->delete();

        return redirect()->route('examinations.index')->with('success', 'Examination removed.');
    }

    /**
     * Per-room Proctor / Room Examiner / Supervising Examiner breakdown for one venue.
     * A Supervising Examiner is only ever assigned to the first ("anchor") room of a
     * group of ROOMS_PER_SUPERVISOR consecutive rooms (see StaffingRandomizer), so
     * every room in that group is credited with that anchor's supervisor.
     *
     * @param  Collection  $rooms  ExamRoom models for one venue
     * @param  Collection  $venueAssignments  rows: ['id', 'role', 'exam_room_id', 'member_name']
     * @return Collection<int, array{id:int,room_number:string,capacity:int,designation:?string,proctor:?string,proctor_assignment_id:?int,room_examiner:?string,room_examiner_assignment_id:?int,supervising_examiner:?string,supervising_examiner_assignment_id:?int,is_supervisor_anchor:bool,complete:bool}>
     */
    private function buildRoomBreakdown(Collection $rooms, Collection $venueAssignments): Collection
    {
        $sortedRooms = $rooms->sortBy(fn ($room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)->values();
        $supervisorByAnchorRoomId = $venueAssignments->where('role', ExamRole::SupervisingExaminer->value)->keyBy('exam_room_id');

        return $sortedRooms->values()->map(function ($room, $index) use ($sortedRooms, $venueAssignments, $supervisorByAnchorRoomId) {
            $groupStart = intdiv($index, self::ROOMS_PER_SUPERVISOR) * self::ROOMS_PER_SUPERVISOR;
            $anchorRoom = $sortedRooms->get($groupStart);
            $supervisor = $anchorRoom ? $supervisorByAnchorRoomId->get($anchorRoom->id) : null;
            $proctor = $venueAssignments->first(fn ($a) => $a['role'] === ExamRole::Proctor->value && $a['exam_room_id'] === $room->id);
            $roomExaminer = $venueAssignments->first(fn ($a) => $a['role'] === ExamRole::RoomExaminer->value && $a['exam_room_id'] === $room->id);

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'capacity' => $room->capacity,
                'designation' => $room->designation,
                'proctor' => $proctor['member_name'] ?? null,
                'proctor_assignment_id' => $proctor['id'] ?? null,
                'room_examiner' => $roomExaminer['member_name'] ?? null,
                'room_examiner_assignment_id' => $roomExaminer['id'] ?? null,
                'supervising_examiner' => $supervisor['member_name'] ?? null,
                'supervising_examiner_assignment_id' => $supervisor['id'] ?? null,
                'is_supervisor_anchor' => $index === $groupStart,
                'complete' => (bool) ($proctor && $roomExaminer && $supervisor),
            ];
        })->values();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['required', 'integer', 'exists:exam_types,id'],
            'exam_date' => ['required', 'date'],
        ]);

        $data['type'] = ExamType::findOrFail($data['exam_type_id'])->name;

        return $data;
    }
}
