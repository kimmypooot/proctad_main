<?php

namespace App\Http\Controllers;

use App\Enums\ExamRole;
use App\Models\Evaluation;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\Member;
use App\Services\SupervisionHierarchyResolver;
use App\Support\DesignationValue;
use App\Support\EvaluationCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    /** Designations this evaluation form covers. */
    private const DESIGNATIONS = [
        ExamRole::ChiefExaminer,
        ExamRole::SupervisingExaminer,
        ExamRole::Proctor,
        ExamRole::RoomExaminer,
    ];

    /**
     * Public page — no login required. The respondent picks the examination,
     * then searches for and selects their own assignment record; everything
     * else (designation, room, hierarchy) is derived server-side from there.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Evaluations/Create', [
            'examinations' => Examination::query()
                ->orderByDesc('exam_date')
                ->limit(50)
                ->get(['id', 'title', 'exam_date'])
                ->map(fn (Examination $exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'exam_date' => $exam->exam_date->format('F j, Y'),
                ]),
            'criteria' => EvaluationCriteria::toArray(),
            // A signed-in member should not have to search for themselves: the
            // system already knows who they are and which examinations they
            // attended. Their own assignments replace the examination picker and
            // the name search entirely — see the page component.
            //
            // The anonymous flow is untouched for everyone else, because this
            // page must keep working with no login at all on exam day.
            'myAssignments' => $this->ownEligibleAssignments($request),
            // Distinguishes "a member with nothing left to evaluate" — who should
            // be told so — from a guest or a staff user without a member record,
            // who still needs the search.
            'isMember' => $request->user()?->member !== null,
            // Served, but attendance not yet recorded. CSC examinations are
            // half-day, so the whole cycle lands in one afternoon: a respondent
            // is often free to evaluate while the secretariat is still working
            // through attendance scans. Without this the page says "nothing to
            // evaluate", which is true but reads as though the assignment is
            // missing — and the member goes home instead of asking.
            'awaitingAttendance' => $this->ownAssignmentsAwaitingAttendance($request),
        ]);
    }

    /**
     * The member's own assignments in a covered designation whose attendance has
     * not been recorded yet — they served, but cannot evaluate until a Testing
     * Center confirms it.
     *
     * Past examinations only: a future one is not waiting on anything.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownAssignmentsAwaitingAttendance(Request $request): array
    {
        $member = $request->user()?->member;

        if ($member === null) {
            return [];
        }

        return ExamAssignment::query()
            ->where('member_id', $member->id)
            ->whereIn('role', array_column(ExamRole::evaluableCases(), 'value'))
            ->whereNull('attendance_confirmed_at')
            ->whereHas('examination', fn ($q) => $q->whereDate('exam_date', '<=', today()))
            ->with('examination:id,title,exam_date')
            ->get()
            ->sortByDesc(fn (ExamAssignment $a) => $a->examination?->exam_date)
            ->values()
            ->map(fn (ExamAssignment $a) => [
                'exam_title' => $a->examination?->title,
                'exam_date' => $a->examination?->exam_date?->format('F j, Y'),
                'designation_label' => $this->designationLabel($a->role),
            ])
            ->all();
    }

    /**
     * The signed-in member's own evaluable assignments, under exactly the
     * conditions resolve() enforces: a covered designation, and attendance
     * confirmed for that exam day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownEligibleAssignments(Request $request): array
    {
        $member = $request->user()
            ? Member::where('user_id', $request->user()->id)->first()
            : null;

        if ($member === null) {
            return [];
        }

        return ExamAssignment::query()
            ->awaitingEvaluationFor($member->id)
            ->with('examination:id,title,exam_date')
            ->get()
            ->sortByDesc(fn (ExamAssignment $a) => $a->examination?->exam_date)
            ->values()
            ->map(fn (ExamAssignment $a) => [
                'id' => $a->id,
                'examination_id' => $a->examination_id,
                'exam_title' => $a->examination?->title,
                'exam_date' => $a->examination?->exam_date?->format('F j, Y'),
                'designation_label' => $this->designationLabel($a->role),
            ])
            ->all();
    }

    /**
     * Typeahead: assignments for the given examination matching a name or
     * PROCTAD ID, restricted to those whose attendance was confirmed that
     * exam day — an unconfirmed or reassigned person can't self-select.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            // Four characters, not one. A single letter returned ten real
            // people per request, which made this endpoint a directory of the
            // examination's staff rather than a way to find yourself in it.
            'q' => ['required', 'string', 'min:4', 'max:100'],
        ]);

        $term = $validated['q'];

        $assignments = ExamAssignment::query()
            ->where('examination_id', $validated['examination_id'])
            ->whereIn('role', array_column(self::DESIGNATIONS, 'value'))
            ->whereNotNull('attendance_confirmed_at')
            ->whereHas('member', function ($query) use ($term) {
                // Anchored, not "contains": a respondent knows their own name
                // and PROCTAD ID and types them from the start. A leading
                // wildcard turns any common substring into a bulk query, which
                // is the difference between confirming an identity you hold and
                // discovering identities you don't.
                $query->where('proctad_id', $term)
                    ->orWhere('first_name', 'like', "{$term}%")
                    ->orWhere('last_name', 'like', "{$term}%");
            })
            ->with(['member', 'room', 'examinationSchool.school'])
            ->limit(10)
            ->get();

        return response()->json([
            'results' => $assignments->map(fn (ExamAssignment $a) => [
                'id' => $a->id,
                'name' => $a->member->name,
                'proctad_id' => $a->member->proctad_id,
                'role_label' => $this->designationLabel($a->role),
                'room_no' => $a->room?->room_number,
                'school_name' => $a->examinationSchool?->school?->name,
            ]),
        ]);
    }

    /**
     * Resolve the full evaluation context for a selected assignment:
     * designation, field office, school, room, and — for a Supervising
     * Examiner — the Room Examiners/Proctors positionally inferred as theirs
     * (see SupervisionHierarchyResolver for the caveats on that inference).
     */
    public function resolve(Request $request, ExamAssignment $assignment, SupervisionHierarchyResolver $resolver): JsonResponse
    {
        abort_unless(
            $assignment->role->isAnyOf(self::DESIGNATIONS) && $assignment->attendance_confirmed_at !== null,
            404,
        );

        $this->rememberResolved($request, $assignment);

        $assignment->loadMissing('member', 'room', 'examinationSchool.school', 'fieldOffice');

        $subordinates = $assignment->role->is(ExamRole::SupervisingExaminer)
            ? $resolver->subordinatesFor($assignment)
            : collect();

        return response()->json([
            'exam_assignment_id' => $assignment->id,
            'respondent_name' => $assignment->member->name,
            'designation' => [
                'value' => $assignment->role->value,
                'label' => $this->designationLabel($assignment->role),
            ],
            'field_office' => $assignment->fieldOffice ? ['id' => $assignment->fieldOffice->id, 'name' => $assignment->fieldOffice->name] : null,
            'school' => $assignment->examinationSchool?->school
                ? ['id' => $assignment->examinationSchool->school->id, 'name' => $assignment->examinationSchool->school->name]
                : null,
            'room_no' => $assignment->room?->room_number,
            'subordinates' => $subordinates->values()->map(fn (ExamAssignment $s) => [
                'exam_assignment_id' => $s->id,
                'room_no' => $s->room?->room_number,
                'ratee_name' => $s->member->name,
            ]),
            // Everyone at this venue the respondent could legitimately rate.
            // The names above are the positional inference's best guess and are
            // pre-selected; this is what the picker offers when that guess is
            // wrong or incomplete — which SupervisionHierarchyResolver warns it
            // can be whenever staffing was done manually room-by-room.
            //
            // A picker rather than a free-text name because a typed name submits
            // with no exam_assignment_id, and PerformanceRatingCalculator matches
            // ratings by that id alone: the rating would attach to nobody, and
            // nothing would report it.
            'available_ratees' => $this->rateeOptionsFor($assignment),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'exam_assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
        ]);

        /** @var ExamAssignment $assignment */
        $assignment = ExamAssignment::with('member', 'examinationSchool')
            ->findOrFail($validated['exam_assignment_id']);

        abort_unless($assignment->role->isAnyOf(self::DESIGNATIONS), 422, 'This assignment is not eligible for evaluation.');

        // The submission has to belong to the person making it. Previously any
        // existing assignment id was accepted, so an evaluation could be filed
        // in a stranger's name — and it is stored under their name (see
        // respondent_name below) and consumed by PerformanceRatingCalculator,
        // which feeds service history and accreditation.
        $this->authorizeRespondent($request, $assignment);

        // resolve() has always required this and store() never did, so a
        // submission could be filed against an assignment the form itself
        // would refuse to open.
        abort_unless(
            $assignment->attendance_confirmed_at !== null,
            422,
            'Attendance for this assignment has not been confirmed yet.',
        );

        // One respondent, one evaluation. The database carries a unique index
        // as well; this is here to answer with something a person can read
        // rather than an integrity-constraint failure.
        abort_if(
            Evaluation::where('exam_assignment_id', $assignment->id)->exists(),
            409,
            'An evaluation has already been submitted for this assignment.',
        );

        $designation = $assignment->role->value;

        $rules = [];

        if ($designation === ExamRole::SupervisingExaminer->value) {
            // Every rated person must be a real assignment at this venue.
            // Previously nullable, and the name was free text: a typo produced a
            // rating with no exam_assignment_id, which PerformanceRatingCalculator
            // matches on exclusively — so the rating attached to nobody and
            // nothing reported it. Constrained to the venue's own staff so a
            // submitted id cannot point at someone the respondent never worked with.
            $rateeIds = $this->rateeOptionsFor($assignment)->pluck('exam_assignment_id')->all();

            $rules += [
                'room_ratings' => ['required', 'array', 'min:1'],
                'room_ratings.*.exam_assignment_id' => ['required', 'integer', Rule::in($rateeIds)],
                'room_ratings.*.room_no' => ['required', 'string', 'max:50'],
                'room_ratings.*.ratee_name' => ['required', 'string', 'max:255'],
                'room_ratings.*.punctuality' => ['required', 'array', 'size:'.count(EvaluationCriteria::PUNCTUALITY)],
                'room_ratings.*.punctuality.*' => ['required', 'integer', 'between:1,5'],
                'room_ratings.*.decorum' => ['required', 'array', 'size:'.count(EvaluationCriteria::DECORUM)],
                'room_ratings.*.decorum.*' => ['required', 'integer', 'between:1,5'],
                'room_ratings.*.procedures' => ['required', 'array', 'size:'.count(EvaluationCriteria::PROCEDURES)],
                'room_ratings.*.procedures.*' => ['required', 'integer', 'between:1,5'],
                'room_ratings.*.comment' => ['nullable', 'string', 'max:2000'],
                'room_readiness' => ['required', 'array', 'size:'.count(EvaluationCriteria::ROOM_READINESS)],
                'room_readiness.*' => ['boolean'],
            ] + $this->examinationAdministrationRules();
        } elseif (in_array($designation, [ExamRole::Proctor->value, ExamRole::RoomExaminer->value], true)) {
            $rules += [
                'room_readiness' => ['required', 'array', 'size:'.count(EvaluationCriteria::ROOM_READINESS)],
                'room_readiness.*' => ['boolean'],
                'exam_preparation' => ['required', 'array', 'size:'.count(EvaluationCriteria::EXAM_PREPARATION)],
                'exam_preparation.*' => ['required', 'integer', 'between:1,5'],
            ];
        } elseif ($designation === ExamRole::ChiefExaminer->value) {
            $rules += $this->examinationAdministrationRules();
        }

        $validated = array_merge($validated, $request->validate($rules));

        Evaluation::create($validated + [
            'respondent_name' => $assignment->member->name,
            'designation' => $designation,
            'field_office_id' => $assignment->field_office_id,
            'school_id' => $assignment->examinationSchool?->school_id,
        ]);

        return back()->with('success', 'Thank you — your evaluation has been submitted.');
    }

    /** Session key holding the assignments this visitor has opened the form for. */
    private const RESOLVED_SESSION_KEY = 'evaluation_resolved';

    /** How many assignments one session may hold open at once. */
    private const RESOLVED_LIMIT = 10;

    /**
     * Records that this session opened the evaluation form for an assignment.
     *
     * This is what store() checks for anonymous respondents. It is not proof of
     * identity — anyone who can reach resolve() can reach store() — but it does
     * mean a submission has to be preceded by the same lookup the UI performs,
     * which bounds forgery to the throttled lookup rate instead of leaving it
     * free. The real fix is an emailed signed link per assignment, the way
     * AssignmentConfirmationSender already works; this is the step that does
     * not require the evaluation form to change how respondents reach it.
     */
    private function rememberResolved(Request $request, ExamAssignment $assignment): void
    {
        $resolved = collect($request->session()->get(self::RESOLVED_SESSION_KEY, []))
            ->push($assignment->id)
            ->unique()
            // Oldest first out: a venue's supervising examiner legitimately
            // opens several, but not an unbounded number, and an unbounded
            // list would let one session accumulate the whole examination.
            ->take(-self::RESOLVED_LIMIT)
            ->values()
            ->all();

        $request->session()->put(self::RESOLVED_SESSION_KEY, $resolved);
    }

    /**
     * A signed-in member may only submit for their own assignment; everyone
     * else must have opened the form for it in this session.
     *
     * Mirrors MyProctadController::respondToAssignment, which applies the same
     * own-record rule to the other public-by-signed-link workflow.
     */
    private function authorizeRespondent(Request $request, ExamAssignment $assignment): void
    {
        if ($member = $request->user()?->member) {
            abort_unless($assignment->member_id === $member->id, 403);

            return;
        }

        abort_unless(
            in_array(
                $assignment->id,
                $request->session()->get(self::RESOLVED_SESSION_KEY, []),
                true,
            ),
            403,
            'Please look up your assignment again before submitting this evaluation.',
        );
    }

    /**
     * Room Examiners and Proctors assigned to the same venue — the people a
     * respondent may rate.
     *
     * Deliberately NOT filtered on confirmed attendance, unlike the respondent's
     * own eligibility. Evaluations are filled in on or just after exam day while
     * attendance confirmation is a separate administrative step that lags behind
     * it, so filtering here empties the roster at exactly the moment the form is
     * used. The supervising examiner is the person who watched who turned up;
     * they select from the assigned roster rather than being blocked by
     * paperwork that has not caught up.
     *
     * Scoped to the venue rather than the examination: a supervising examiner
     * has no business rating staff at a testing centre they were never at. This
     * is also the set store() validates a submitted ratee against.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rateeOptionsFor(ExamAssignment $assignment): Collection
    {
        if ($assignment->examination_school_id === null) {
            return collect();
        }

        return ExamAssignment::query()
            ->where('examination_school_id', $assignment->examination_school_id)
            ->whereIn('role', [ExamRole::RoomExaminer->value, ExamRole::Proctor->value])
            ->with('member:id,first_name,middle_name,last_name,suffix', 'room:id,room_number')
            ->get()
            ->sortBy(fn (ExamAssignment $a) => sprintf(
                '%06d-%s',
                (int) preg_replace('/\D/', '', (string) $a->room?->room_number),
                $a->member?->name,
            ))
            ->values()
            ->map(fn (ExamAssignment $a) => [
                'exam_assignment_id' => $a->id,
                'room_no' => $a->room?->room_number,
                'ratee_name' => $a->member?->name,
                'role_label' => $a->role->label(),
                // Shown in the picker so the respondent can see whose attendance
                // is still unconfirmed, without it excluding them.
                'attendance_confirmed' => $a->attendance_confirmed_at !== null,
            ]);
    }

    /**
     * Accepts either a built-in case or a stored designation, since assignments
     * now carry a DesignationValue that may not correspond to an ExamRole.
     */
    private function designationLabel(ExamRole|DesignationValue $role): string
    {
        $isProctor = $role instanceof DesignationValue
            ? $role->is(ExamRole::Proctor)
            : $role === ExamRole::Proctor;

        return $isProctor ? 'Room Proctor' : $role->label();
    }

    private function examinationAdministrationRules(): array
    {
        return [
            'venue_readiness' => ['required', 'array', 'size:'.count(EvaluationCriteria::VENUE_READINESS)],
            'venue_readiness.*' => ['required', 'integer', 'between:1,5'],
            'venue_comment' => ['nullable', 'string', 'max:2000'],
            'committee_coordination' => ['required', 'array', 'size:'.count(EvaluationCriteria::COMMITTEE_COORDINATION)],
            'committee_coordination.*' => ['required', 'integer', 'between:1,5'],
            'committee_comment' => ['nullable', 'string', 'max:2000'],
            'conduct_of_exam' => ['required', 'array', 'size:'.count(EvaluationCriteria::CONDUCT_OF_EXAM)],
            'conduct_of_exam.*' => ['required', 'integer', 'between:1,5'],
            'conduct_comment' => ['nullable', 'string', 'max:2000'],
            'examinee_experience' => ['required', 'array', 'size:'.count(EvaluationCriteria::EXAMINEE_EXPERIENCE)],
            'examinee_experience.*' => ['required', 'integer', 'between:1,5'],
            'examinee_comment' => ['nullable', 'string', 'max:2000'],
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'what_worked' => ['nullable', 'string', 'max:2000'],
            'challenges' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'suggestions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
