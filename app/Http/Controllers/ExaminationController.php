<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\BlacklistStatus;
use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Exports\RoomAssignmentsExport;
use App\Models\AuditLog;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamType;
use App\Models\Member;
use App\Models\OepAssignment;
use App\Models\OepAttendance;
use App\Models\OtherExaminationPersonnel;
use App\Models\School;
use App\Services\PerformanceRatingCalculator;
use App\Services\RoomStaffingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExaminationController extends Controller
{
    public function __construct(private RoomStaffingCalculator $roomStaffing) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Examination::class);

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        $examinations = Examination::withCount([
            'assignments' => fn ($q) => $q->when($foScoped, fn ($q2) => $q2->where('field_office_id', $user->field_office_id)),
            'assignments as confirmed_assignments_count' => fn ($q) => $q->where('status', 'confirmed')
                ->when($foScoped, fn ($q2) => $q2->where('field_office_id', $user->field_office_id)),
        ])
            ->with(['venues' => function ($query) use ($foScoped, $user) {
                $query->with('rooms:id,examination_school_id')
                    ->when($foScoped, fn ($q) => $q->whereHas('school', fn ($s) => $s->where('field_office_id', $user->field_office_id)));
            }])
            ->orderByDesc('exam_date')
            ->get();

        $now = now()->startOfDay();

        return Inertia::render('Examinations/Index', [
            'examinations' => $examinations->map(function (Examination $exam) use ($now) {
                $roomsCount = $exam->venues->sum(fn ($venue) => $venue->rooms->count());

                $status = match (true) {
                    $exam->exam_date->isFuture() => 'upcoming',
                    $exam->exam_date->isToday() => 'ongoing',
                    default => 'completed',
                };

                return [
                    ...$exam->only('id', 'title', 'type', 'exam_type_id', 'assignments_count', 'is_active'),
                    'exam_date' => $exam->exam_date->toDateString(),
                    'status' => $status,
                    'venues_count' => $exam->venues->count(),
                    'rooms_count' => $roomsCount,
                    'venue_names' => $exam->venues->map(fn ($v) => $v->school?->name)->filter()->values(),
                    'confirmed_count' => $exam->confirmed_assignments_count,
                    'staffing_ratio' => $exam->assignments_count > 0
                        ? round(($exam->confirmed_assignments_count / $exam->assignments_count) * 100)
                        : null,
                ];
            }),
            'stats' => [
                'total' => $examinations->count(),
                'upcoming' => $examinations->filter(fn ($exam) => $exam->exam_date->isFuture())->count(),
                'ongoing' => $examinations->filter(fn ($exam) => $exam->exam_date->isToday())->count(),
                'completed' => $examinations->filter(fn ($exam) => $exam->exam_date->isPast())->count(),
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

    public function show(Request $request, Examination $examination, PerformanceRatingCalculator $ratingCalculator): Response
    {
        Gate::authorize('view', $examination);

        $user = $request->user();
        $foScoped = $user->role->isFieldOfficeScoped();

        $assignmentModels = $examination->assignments()
            ->with(
                'member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'fieldOffice:id,name,code',
                'examinationSchool.school:id,name',
                'room:id,room_number',
                'coveredSchools.school:id,name',
                'attendances',
            )
            ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->get();

        $computedRatings = $ratingCalculator->computeForMany($assignmentModels);

        $assignments = $assignmentModels
            ->map(function (ExamAssignment $assignment) use ($user, $computedRatings) {
                $computed = $computedRatings[$assignment->id] ?? null;
                $rating = $computed['rating'] ?? $assignment->performance_rating;

                return [
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
                    'rating' => $rating?->value,
                    'rating_label' => $rating?->label(),
                    'rating_variant' => $rating?->badgeVariant(),
                    'manual_rating' => $assignment->performance_rating?->value,
                    'rating_is_computed' => $computed !== null,
                    'rating_computed_average' => $computed['average'] ?? null,
                    'rating_computed_count' => $computed['ratings_count'] ?? null,
                    'remarks' => $assignment->remarks,
                    'can_manage' => $user->can('update', $assignment),
                    'is_coverage_role' => $assignment->isCoverageRole(),
                    'covered_schools' => $assignment->coveredSchools->map(fn ($school) => [
                        'id' => $school->id,
                        'name' => $school->school?->name,
                        'attended' => $assignment->attendances->contains('examination_school_id', $school->id),
                    ]),
                ];
            });

        // Members the current user may assign that aren't on this exam yet.
        $assignable = $user->can('create', ExamAssignment::class)
            ? Member::query()
                ->where('status', 'active')
                ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->whereDoesntHave('assignments', fn ($q) => $q->where('examination_id', $examination->id))
                ->whereDoesntHave('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))
                ->orderBy('last_name')
                ->get(['id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'field_office_id'])
                ->map(fn (Member $member) => [
                    'id' => $member->id,
                    'label' => "{$member->name} ({$member->proctad_id})",
                ])
            : [];

        $roomRoles = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value, ExamRole::SupervisingExaminer->value];

        // Room-role assignments for the venue breakdown/staffing below are
        // intentionally NOT filtered by the assignment's own field_office_id
        // (unlike $assignments above, used for the flat Assignments table).
        // A room-role assignee can legitimately belong to a different office
        // than the venue they're staffing (e.g. proctoring at another FO's
        // school) — venue access is already correctly gated by the venue's
        // *own* field office just below, so re-filtering by the assignee's
        // office here would incorrectly hide people actually placed in a
        // room at a venue this admin does manage (matches ExamRoomController,
        // which has no such filter either).
        $roomAssignments = $examination->assignments()
            ->whereIn('role', $roomRoles)
            ->with('member:id,first_name,middle_name,last_name,suffix')
            ->get()
            ->map(fn (ExamAssignment $a) => [
                'id' => $a->id,
                'examination_school_id' => $a->examination_school_id,
                'role' => $a->role->value,
                'exam_room_id' => $a->exam_room_id,
                'status' => $a->status->value,
                'member_name' => $a->member?->name,
            ]);

        $venues = $examination->venues()
            ->with('school:id,name,municipality,field_office_id', 'rooms', 'oepAssignments.personnel:id,oep_id,first_name,middle_name,last_name,suffix,personnel_type')
            ->when($foScoped, fn ($q) => $q->whereHas('school', fn ($s) => $s->where('field_office_id', $user->field_office_id)))
            ->get()
            ->map(function (ExaminationSchool $venue) use ($roomAssignments, $roomRoles) {
                $normalizedAssignments = $roomAssignments->filter(fn ($a) => $a['examination_school_id'] === $venue->id);
                $eligibleAssignments = $this->roomStaffing->eligible($normalizedAssignments);
                // Room assignment is a member's LAST step, only reachable once
                // they've confirmed their availability — a still-Pending person
                // isn't offered here even though they otherwise count toward
                // venue staffing totals (see $eligibleAssignments above).
                $unassignedPool = collect($roomRoles)->mapWithKeys(fn ($role) => [
                    $role => $eligibleAssignments
                        ->where('role', $role)
                        ->where('status', AssignmentStatus::Confirmed->value)
                        ->whereNull('exam_room_id')
                        ->map(fn ($a) => ['id' => $a['id'], 'name' => $a['member_name']])
                        ->values(),
                ]);

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
                    'rooms_count' => $venue->rooms->count(),
                    'total_capacity' => $venue->rooms->sum('capacity'),
                    'room_breakdown' => $this->roomStaffing->breakdown($venue->rooms, $normalizedAssignments),
                    'unassigned_pool' => $unassignedPool,
                    'staffing' => $this->roomStaffing->stats($venue->rooms, $normalizedAssignments),
                    'oep_assignments' => $venue->oepAssignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'name' => $assignment->personnel?->name,
                        'oep_id' => $assignment->personnel?->oep_id,
                        'personnel_type_label' => $assignment->personnel?->personnel_type?->label(),
                        'role_group' => $assignment->personnel?->personnel_type?->group()->value,
                        'role_group_label' => $assignment->personnel?->personnel_type?->group()->label(),
                        'present' => $assignment->personnel && OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
                            ->where('examination_school_id', $assignment->examination_school_id)
                            ->exists(),
                    ]),
                ];
            });

        $availableOep = $user->can('create', OepAssignment::class)
            ? OtherExaminationPersonnel::query()
                ->where('is_active', true)
                ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id))
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'oep_id'])
                ->map(fn (OtherExaminationPersonnel $oep) => ['value' => $oep->id, 'label' => "{$oep->name} ({$oep->oep_id})"])
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
            'availableOep' => $availableOep,
            'reportsGenerated' => AuditLog::where('auditable_type', Examination::class)
                ->where('auditable_id', $examination->id)
                ->where('action', 'report_generated')
                ->exists(),
            'can' => [
                'assign' => $user->can('create', ExamAssignment::class),
                'manageOep' => $user->can('create', OepAssignment::class),
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
                'status' => $a->status->value,
                'member_name' => $a->member?->name,
            ]);

        $rows = collect();
        $venueLabel = null;

        foreach ($venues as $venue) {
            $venueAssignments = $assignments->where('examination_school_id', $venue->id);

            foreach ($this->roomStaffing->breakdown($venue->rooms, $venueAssignments) as $room) {
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

    public function toggleVisibility(Request $request, Examination $examination): RedirectResponse
    {
        Gate::authorize('update', $examination);

        $examination->update(['is_active' => ! $examination->is_active]);

        return back()->with('success', $examination->is_active ? 'Examination is now visible in dropdowns.' : 'Examination hidden from dropdowns.');
    }

    public function destroy(Examination $examination): RedirectResponse
    {
        Gate::authorize('delete', $examination);

        $examination->delete();

        return redirect()->route('examinations.index')->with('success', 'Examination removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['required', 'integer', 'exists:exam_types,id'],
            'exam_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['type'] = ExamType::findOrFail($data['exam_type_id'])->name;

        return $data;
    }
}
