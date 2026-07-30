<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\BlacklistStatus;
use App\Enums\ConfirmationAction;
use App\Enums\ExamRole;
use App\Enums\ExamRoleGroup;
use App\Enums\MemberStatus;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Jobs\SendAssignmentConfirmation;
use App\Models\AuditLog;
use App\Models\Blacklist;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\Member;
use App\Models\User;
use App\Services\AlternateActivator;
use App\Services\AssignmentConfirmationSender;
use App\Services\AssignmentResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExamAssignmentController extends Controller
{
    // Proctor/Room Examiner/Supervising Examiner physically staff one specific
    // school, so (unlike REC, which monitors region-wide) they must always be
    // drawn from that venue's own testing center. LEC is center-bound too, but
    // at the coverage level — see coveredSchoolJurisdictionRule.
    private const ROOM_ROLES = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value, ExamRole::SupervisingExaminer->value];

    // Exactly one Proctor and one Room Examiner per room (Supervising Examiner
    // is anchor-group based — see RoomStaffingCalculator — and isn't a simple
    // one-per-room slot, so it's deliberately excluded here).
    private const ONE_PER_ROOM_ROLES = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value];

    /**
     * Builds a validation closure for `exam_room_id` that (a) requires the
     * member to have already CONFIRMED their assignment before a room can be
     * set — a test administrator only learns their room once they've said
     * they're actually available — and (b) for Proctor/Room Examiner, rejects
     * a room that's already staffed for that role.
     */
    private function roomAssignabilityRule(?string $role, AssignmentStatus $currentStatus, ?int $excludeAssignmentId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role, $currentStatus, $excludeAssignmentId) {
            if (! $value) {
                return;
            }

            if ($currentStatus !== AssignmentStatus::Confirmed) {
                $fail('This assignment must be confirmed by the member before a room can be assigned.');

                return;
            }

            if (! in_array($role, self::ONE_PER_ROOM_ROLES, true)) {
                return;
            }

            $taken = ExamAssignment::where('exam_room_id', $value)
                ->where('role', $role)
                ->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value])
                // A no-show does not hold the room. Without this, the seat they
                // vacated stays blocked and the alternate called in to cover it
                // cannot be given the room they are standing in.
                ->whereNull('marked_absent_at')
                ->when($excludeAssignmentId, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
                ->exists();

            if ($taken) {
                $fail(ExamRole::from($role)->label().' is already assigned to this room.');
            }
        };
    }

    /** Builds a validation closure for the `examination_school_id` field enforcing that rule. */
    private function venueJurisdictionRule(Request $request, array $memberIds): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $memberIds) {
            if (! $value || ! in_array($request->input('role'), self::ROOM_ROLES, true)) {
                return;
            }

            $centerId = ExaminationSchool::find($value)?->school?->testing_center_id;
            if ($centerId === null) {
                return;
            }

            // Members serve the testing center they registered at, so the
            // comparison is center to center — a member registered at Tacloban
            // City may staff a room there whichever Leyte office administers it.
            // Exempt are members who are also region-wide CSC staff, who may be
            // placed at any venue (see Member::isRegionWide).
            $confined = Member::with('user:id,role,field_office_id')
                ->whereIn('id', array_filter($memberIds))
                ->notServingRegionWide()
                ->get();

            foreach ($confined as $member) {
                /*
                 * A field office employee's centers come from their office, the
                 * same authority staffJurisdictionRule uses. Their registry
                 * record often carries no center of its own — nothing about
                 * their posting decides one — and reading only that column
                 * grouped them with "wrong center" and refused them every venue
                 * in the region, their own office's included.
                 */
                $ownCenters = $member->testing_center_id !== null
                    ? [$member->testing_center_id]
                    : ($member->user?->scopedTestingCenterIds() ?? []);

                if (! in_array($centerId, $ownCenters, true)) {
                    $fail('Proctor, Room Examiner, and Supervising Examiner assignments must stay within the venue\'s testing center.');

                    return;
                }
            }
        };
    }

    /**
     * Builds a validation closure for `covered_school_ids` enforcing the
     * REC/LEC split.
     *
     * REC (and Chief Examiner for Investigation) monitor region-wide, so their
     * covered schools are unrestricted. LEC is seated at one testing center —
     * which school inside it they end up at is decided later, but it never
     * leaves that center — so every covered school must share the testing
     * center of the assignment's own venue.
     */
    private function coveredSchoolJurisdictionRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request) {
            if (! is_array($value) || $value === []) {
                return;
            }

            if (ExamRole::tryFrom((string) $request->input('role'))?->group() !== ExamRoleGroup::TestingCenter) {
                return;
            }

            // Without a venue there is no center to measure the coverage
            // against, so the list cannot be validated rather than silently
            // accepted.
            $centerId = ExaminationSchool::find($request->input('examination_school_id'))?->school?->testing_center_id;

            if ($centerId === null) {
                $fail('Set this assignment\'s venue before choosing covered schools for a Local Examination Committee role.');

                return;
            }

            $outsideCenter = ExaminationSchool::whereIn('id', $value)
                ->whereHas('school', fn ($q) => $q->where('testing_center_id', '!=', $centerId))
                ->exists();

            if ($outsideCenter) {
                $fail('Local Examination Committee assignments can only cover schools within their own testing center.');
            }
        };
    }

    /**
     * Builds a validation closure for `member_id` enforcing the ex officio REC
     * seats: the committee is chaired by the Director IV and co-chaired by the
     * Director III, so those two designations cannot be staffed from the pool.
     *
     * Deliberately not a hard constraint. When the office is vacant, or its
     * holder is not enrolled in the registry, Member::holdingOffice() returns
     * null and the seat falls open — otherwise an acting appointment or an
     * un-enrolled director would leave the committee unstaffable, with no way
     * out except editing the database.
     */
    private function reservedSeatRule(?string $role): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role) {
            $office = ExamRole::tryFrom((string) $role)?->reservedForRole();

            if ($office === null) {
                return;
            }

            $incumbent = Member::holdingOffice($office);

            if ($incumbent === null || (int) $value === $incumbent->id) {
                return;
            }

            $fail(sprintf(
                '%s is held ex officio by the %s (%s).',
                ExamRole::from($role)->label(),
                $office->label(),
                $incumbent->name,
            ));
        };
    }

    /**
     * Confines field-office-scoped staff to the testing centers they serve.
     *
     * A Field Office Staff or Field Director who is also an accredited member
     * serves where they work — unlike the regional roles (Super Admin, ESD
     * Admin, the two directors), who may be assigned anywhere in the region,
     * and unlike ordinary members, whose only jurisdiction limit is the
     * room-role rule in venueJurisdictionRule.
     *
     * Stricter than that rule in two ways, both deliberate: it covers every
     * designation rather than just the room-level ones, and it rejects an
     * assignment with no venue at all — a venue-less seat is a regional one,
     * which these staff cannot hold.
     *
     * @param  array<int, mixed>  $memberIds
     */
    private function staffJurisdictionRule(Request $request, array $memberIds): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $memberIds) {
            $members = Member::with('user:id,role,field_office_id')
                ->whereIn('id', array_filter((array) $memberIds))
                ->get()
                // Ordinary members, and staff with region-wide reach, are
                // unaffected — only the field-office-scoped roles are confined.
                ->filter(fn (Member $member) => (bool) $member->user?->role?->isFieldOfficeScoped());

            if ($members->isEmpty()) {
                return;
            }

            $venueSchool = ExaminationSchool::find($request->input('examination_school_id'))?->school;
            $venueCenterId = $venueSchool?->testing_center_id;

            foreach ($members as $member) {
                if ($venueSchool === null) {
                    $fail(sprintf(
                        '%s is %s and can only be assigned to a venue within their own jurisdiction, so this assignment needs one.',
                        $member->name,
                        $member->user->role->label(),
                    ));

                    return;
                }

                // The employment record is the authority — the centers their
                // office serves. The registry record's own center is the
                // fallback for a staff account with no office set.
                $ownCenters = $member->user->scopedTestingCenterIds()
                    ?: array_filter([$member->testing_center_id]);

                if ($ownCenters !== [] && ! in_array($venueCenterId, $ownCenters, true)) {
                    $fail(sprintf(
                        '%s is %s and can only be assigned within a testing center they serve.',
                        $member->name,
                        $member->user->role->label(),
                    ));

                    return;
                }
            }
        };
    }

    /**
     * The batch form of reservedSeatRule. A reserved seat is held by exactly
     * one person, so a batch aimed at one may name only them.
     */
    private function reservedSeatBatchRule(?string $role): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role) {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $memberId) {
                $this->reservedSeatRule($role)($attribute, $memberId, $fail);
            }
        };
    }

    public function store(Request $request, Examination $examination, AssignmentConfirmationSender $confirmationSender): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'member_id' => [
                'required',
                Rule::exists('members', 'id'),
                Rule::unique('exam_assignments', 'member_id')
                    ->where('examination_id', $examination->id),
                $this->reservedSeatRule($request->input('role')),
                $this->staffJurisdictionRule($request, [$request->input('member_id')]),
            ],
            'role' => ['required', Rule::enum(ExamRole::class)],
            'examination_school_id' => [
                'nullable',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
                $this->venueJurisdictionRule($request, [$request->input('member_id')]),
            ],
            // No 'required_with:examination_school_id' here: a test administrator
            // only needs to know their venue and role at assignment time — which
            // room they'll staff is only decided closer to exam day (Step 3), and
            // only once they've confirmed (see roomAssignabilityRule).
            'exam_room_id' => [
                'nullable',
                Rule::exists('exam_rooms', 'id')->where('examination_school_id', $request->input('examination_school_id')),
                $this->roomAssignabilityRule($request->input('role'), AssignmentStatus::Pending),
            ],
            'covered_school_ids' => ['nullable', 'array', $this->coveredSchoolJurisdictionRule($request)],
            'covered_school_ids.*' => [
                'integer',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
            ],
        ], [
            'member_id.unique' => 'This member is already assigned to this examination.',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        // FO-scoped staff may only assign members within their jurisdiction —
        // their own testing centers, plus region-wide members.
        abort_if(
            $user->role->isFieldOfficeScoped() && ! $member->isWithinJurisdictionOf($user),
            403,
        );

        abort_if(
            $member->blacklists()->where('status', BlacklistStatus::Active)->exists(),
            422,
            'This member is blacklisted and cannot be assigned.',
        );

        $assignment = $examination->assignments()->create([
            'member_id' => $member->id,
            'role' => $validated['role'],
            'field_office_id' => $member->field_office_id,
            'testing_center_id' => $member->testing_center_id,
            'examination_school_id' => $validated['examination_school_id'] ?? null,
            'exam_room_id' => $validated['exam_room_id'] ?? null,
        ]);

        $this->syncCoveredSchools($assignment, $validated['covered_school_ids'] ?? []);

        // A venue is enough for the member to need to know and confirm —
        // notify them (email + in-app) the moment one is set, rather than
        // waiting on staff to remember to click "Send Confirmation".
        if ($assignment->examination_school_id) {
            $confirmationSender->send($assignment, $user);
        }

        return back()->with('success', "{$member->name} assigned to {$examination->title}.");
    }

    /**
     * Batch-assign N members to one role in a single action — mirrors
     * legacy's "Assign As" bulk picker. Members already assigned to this
     * examination are silently skipped (reported back in the flash message)
     * rather than failing the whole batch.
     */
    public function bulkStore(Request $request, Examination $examination): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            // The reserved-seat check sits on the array, not on `member_ids.*`:
            // a per-element rule reports under `member_ids.0`, which the form
            // does not render, so the batch would fail with no visible reason.
            'member_ids' => [
                'required',
                'array',
                'min:1',
                $this->reservedSeatBatchRule($request->input('role')),
                $this->staffJurisdictionRule($request, $request->input('member_ids', [])),
            ],
            'member_ids.*' => ['integer', Rule::exists('members', 'id')],
            'role' => ['required', Rule::enum(ExamRole::class)],
            'examination_school_id' => [
                'nullable',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
                $this->venueJurisdictionRule($request, $request->input('member_ids', [])),
            ],
            'covered_school_ids' => ['nullable', 'array', $this->coveredSchoolJurisdictionRule($request)],
            'covered_school_ids.*' => [
                'integer',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
            ],
        ]);

        $alreadyAssigned = $examination->assignments()->pluck('member_id')->all();
        $members = Member::query()
            ->whereIn('id', $validated['member_ids'])
            ->where('status', 'active')
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->withinJurisdictionOf($user))
            ->whereDoesntHave('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))
            ->get();

        // Room is deliberately not set here: a test administrator only needs
        // to know their venue and role at assignment time — which room they
        // staff is decided later via Step 3 (see RoomAssignmentPanel).
        $assigned = 0;
        $assignedIds = [];
        foreach ($members as $member) {
            if (in_array($member->id, $alreadyAssigned, true)) {
                continue;
            }

            $assignment = $examination->assignments()->create([
                'member_id' => $member->id,
                'role' => $validated['role'],
                'field_office_id' => $member->field_office_id,
            'testing_center_id' => $member->testing_center_id,
                'examination_school_id' => $validated['examination_school_id'] ?? null,
            ]);
            $this->syncCoveredSchools($assignment, $validated['covered_school_ids'] ?? []);

            // A venue is enough for the member to need to know and confirm —
            // notify them (email + in-app) the moment one is set, rather than
            // waiting on staff to remember to click "Send Confirmation".
            //
            // Queued, unlike the single-assignment paths: staffing a whole venue
            // means one SMTP round-trip per member inside this loop, which is
            // what makes a large deployment time out mid-batch.
            if ($assignment->examination_school_id) {
                SendAssignmentConfirmation::dispatch($assignment->id, $user->id, $request->ip());
            }

            $assigned++;
            $assignedIds[] = $member->id;
        }

        $message = "Assigned {$assigned} member(s).";

        $skippedIds = array_diff($validated['member_ids'], $assignedIds);
        if ($skippedIds) {
            $message .= ' Skipped '.count($skippedIds).': '.$this->describeSkippedMembers($skippedIds, $alreadyAssigned, $user).'.';
        }

        return back()->with('success', $message);
    }

    /**
     * One name-and-reason per skipped member (capped so the flash toast stays
     * readable for large batches) — replaces the old collapsed "N skipped,
     * already assigned or ineligible" message, which gave no way to tell
     * which member was skipped or why.
     */
    private function describeSkippedMembers(array $skippedIds, array $alreadyAssigned, User $user): string
    {
        $blacklistedIds = Blacklist::whereIn('member_id', $skippedIds)
            ->where('status', BlacklistStatus::Active)
            ->pluck('member_id')
            ->all();

        $skippedMembers = Member::whereIn('id', $skippedIds)
            ->with('user.fieldOffice:id,is_regional')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'status', 'field_office_id', 'testing_center_id'])
            ->keyBy('id');

        $descriptions = collect($skippedIds)->map(function ($id) use ($skippedMembers, $alreadyAssigned, $blacklistedIds, $user) {
            $member = $skippedMembers->get($id);
            $name = $member?->name ?? "member #{$id}";

            $reason = match (true) {
                in_array($id, $alreadyAssigned, true) => 'already assigned',
                in_array($id, $blacklistedIds, true) => 'blacklisted',
                $member === null => 'not found',
                $member->status !== MemberStatus::Active => 'inactive',
                $user->role->isFieldOfficeScoped() && ! $member->isWithinJurisdictionOf($user) => 'different testing center',
                default => 'ineligible',
            };

            return "{$name} ({$reason})";
        });

        $limit = 10;
        if ($descriptions->count() > $limit) {
            $remaining = $descriptions->count() - $limit;

            return $descriptions->take($limit)->implode(', ')." and {$remaining} more";
        }

        return $descriptions->implode(', ');
    }

    /**
     * Admin manually confirms one or more pending assignments — mirrors
     * legacy's bulk "Manual Confirm" action, for members who can't confirm
     * via their own emailed link (e.g. no email on file).
     *
     * Goes through AssignmentResponder like every other response, so each one
     * leaves an audit row naming the staff member who recorded it. Use
     * recordResponse() to record a decline, or to note how the answer arrived.
     */
    public function bulkConfirm(Request $request, AssignmentResponder $responder): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', Rule::exists('exam_assignments', 'id')],
        ]);

        $assignments = ExamAssignment::whereIn('id', $validated['assignment_ids'])
            ->where('status', AssignmentStatus::Pending)
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->inJurisdictionOf($user))
            ->get();

        foreach ($assignments as $assignment) {
            $responder->recordOnBehalf($assignment, $user, true, ipAddress: $request->ip());
        }

        return back()->with('success', "Confirmed {$assignments->count()} assignment(s).");
    }

    /**
     * Record, on a member's behalf, the answer they gave off-system.
     *
     * Testing centers are rural and the emailed link expires after seven days,
     * so members regularly answer by phone or in person instead. Without this
     * the office's only options were to leave the seat pending or to
     * bulk-confirm — which could not record a decline at all, and left no
     * trace of who took the call.
     *
     * Not an override of an answer already given: like every other response
     * path it is one-shot (AssignmentResponder), so a member's own confirm or
     * decline stands. Correcting one of those is still Force Reassign or
     * removing the assignment.
     */
    public function recordResponse(Request $request, ExamAssignment $assignment, AssignmentResponder $responder): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $assignment->loadMissing('examination', 'member');

        // Deliberately lt(today()), not isPast(): exam_date is a date, so from
        // 00:01 on the examination's own day isPast() is already true — and
        // exam-day morning is exactly when a member finally gets through.
        abort_if(
            $assignment->examination->exam_date->lt(today()),
            422,
            'Cannot record a response: this examination has already concluded.',
        );

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'decline'])],
            'channel' => ['required', Rule::in(array_keys(AssignmentResponder::ON_BEHALF_CHANNELS))],
            'decline_reason' => ['required_if:action,decline', 'nullable', 'string', 'max:500'],
            // Who was spoken to and when, in the office's own words — this is
            // the only evidence behind a response the member did not make.
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $confirmed = $validated['action'] === 'confirm';

        $recorded = $responder->recordOnBehalf(
            $assignment,
            $request->user(),
            $confirmed,
            $validated['decline_reason'] ?? null,
            $validated['channel'],
            $validated['note'] ?? null,
            $request->ip(),
        );

        if (! $recorded) {
            return back()->with('error', "{$assignment->member?->name} has already responded to this assignment.");
        }

        return back()->with('success', $confirmed
            ? "Confirmation recorded for {$assignment->member?->name}."
            : "Decline recorded for {$assignment->member?->name}. The seat needs re-staffing.");
    }

    /**
     * Exam-day cover. The venue scanner reaches the same operations through
     * ScannerController; both delegate to AlternateActivator so the rules
     * cannot drift between the two front doors.
     */
    public function markAbsent(Request $request, ExamAssignment $assignment, AlternateActivator $activator): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        return $activator->markAbsent($assignment, $request->user())
            ? back()->with('success', "{$assignment->member?->name} marked absent. You can now call in an alternate.")
            : back()->with('error', 'This assignment is already marked absent, or the member has already reported in.');
    }

    public function clearAbsence(ExamAssignment $assignment, AlternateActivator $activator): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        return $activator->clearAbsence($assignment)
            ? back()->with('success', 'Absence cleared.')
            : back()->with('error', 'This absence cannot be cleared while an alternate is covering the seat.');
    }

    public function activateAlternate(Request $request, ExamAssignment $assignment, AlternateActivator $activator): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate([
            'alternate_assignment_id' => ['required', 'integer', Rule::exists('exam_assignments', 'id')],
        ]);

        $alternate = ExamAssignment::findOrFail($validated['alternate_assignment_id']);

        if ($refusal = $activator->cannotActivate($alternate, $assignment)) {
            return back()->with('error', $refusal);
        }

        $activator->activate($alternate, $assignment);

        return back()->with('success', sprintf(
            '%s is now serving as %s in place of %s.',
            $alternate->member?->name,
            $assignment->role->label(),
            $assignment->member?->name,
        ));
    }

    public function standDownAlternate(ExamAssignment $assignment, AlternateActivator $activator): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        if (! $assignment->isSubstitute()) {
            return back()->with('error', 'This assignment is not covering another seat.');
        }

        $activator->standDown($assignment);

        return back()->with('success', "{$assignment->member?->name} returned to the standby pool.");
    }

    public function update(Request $request, ExamAssignment $assignment, AssignmentConfirmationSender $confirmationSender): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $hadVenue = $assignment->examination_school_id !== null;

        $validated = $request->validate([
            'role' => [
                'required',
                Rule::enum(ExamRole::class),
                // Here the member is fixed and the role is what's changing, so
                // the reserved-seat check runs against the existing member —
                // otherwise an edit could promote anyone into the REC chair.
                fn (string $attribute, mixed $value, \Closure $fail) => $this
                    ->reservedSeatRule(is_string($value) ? $value : null)($attribute, $assignment->member_id, $fail),
                // Also re-checked on edit: the venue can be moved out of a
                // field-office-scoped member's own testing centers here just as
                // easily as it can be set wrongly at creation.
                $this->staffJurisdictionRule($request, [$assignment->member_id]),
            ],
            'performance_rating' => ['nullable', Rule::enum(PerformanceRating::class)],
            'remarks' => ['nullable', 'string', 'max:255'],
            'attended' => ['required', 'boolean'],
            'examination_school_id' => [
                'nullable',
                Rule::exists('examination_school', 'id')->where('examination_id', $assignment->examination_id),
                $this->venueJurisdictionRule($request, [$assignment->member_id]),
            ],
            // No 'required_with:examination_school_id' — see store(): room is
            // only decided once the member has confirmed (roomAssignabilityRule).
            'exam_room_id' => [
                'nullable',
                Rule::exists('exam_rooms', 'id')->where('examination_school_id', $request->input('examination_school_id')),
                $this->roomAssignabilityRule($request->input('role'), $assignment->status, $assignment->id),
            ],
            'covered_school_ids' => ['nullable', 'array', $this->coveredSchoolJurisdictionRule($request)],
            'covered_school_ids.*' => [
                'integer',
                Rule::exists('examination_school', 'id')->where('examination_id', $assignment->examination_id),
            ],
        ]);

        $assignment->update([
            'role' => $validated['role'],
            'performance_rating' => $validated['performance_rating'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'examination_school_id' => $validated['examination_school_id'] ?? null,
            'exam_room_id' => $validated['exam_room_id'] ?? null,
            'attendance_confirmed_at' => $validated['attended']
                ? ($assignment->attendance_confirmed_at ?? now())
                : null,
            'attendance_confirmed_by' => $validated['attended']
                ? ($assignment->attendance_confirmed_by ?? $request->user()->id)
                : null,
        ]);

        $this->syncCoveredSchools($assignment->fresh(), $validated['covered_school_ids'] ?? []);

        // Only notify the first time a venue is set here — re-editing an
        // already-notified assignment (e.g. tweaking remarks) shouldn't spam
        // another confirmation email.
        if (! $hadVenue && $assignment->examination_school_id) {
            $confirmationSender->send($assignment, $request->user());
        }

        return back()->with('success', 'Service record updated.');
    }

    /**
     * Manual per-room assignment — the lightweight counterpart to the
     * randomizer: change only WHICH ROOM an already-deployed member is
     * staffing, without touching role/rating/remarks/attendance (unlike the
     * full edit-record form). Powers the Step 4 room staffing map.
     */
    public function assignRoom(Request $request, ExamAssignment $assignment): RedirectResponse
    {
        // Room staffing is a venue-level action (same boundary as the Rooms
        // page and the staffing randomizer), not an assignment-record edit —
        // deliberately NOT gated by ExamAssignmentPolicy::manage(), which
        // checks the assignee's own testing center. That normally matches the
        // venue's via venueJurisdictionRule, but isn't guaranteed for every
        // row (e.g. legacy data), and a venue's FO admin must always be able
        // to room-assign whoever the venue's own unassigned pool shows them.
        $assignment->loadMissing('examinationSchool.school.testingCenter');
        $venueSchool = $assignment->examinationSchool?->school;

        abort_unless(
            $request->user()->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)
                || ($request->user()->role->isFieldOfficeScoped() && $venueSchool?->handledByOffice($request->user()->field_office_id)),
            403,
        );

        $validated = $request->validate([
            'exam_room_id' => [
                'nullable',
                Rule::exists('exam_rooms', 'id')->where('examination_school_id', $assignment->examination_school_id),
                $this->roomAssignabilityRule($assignment->role->value, $assignment->status, $assignment->id),
            ],
        ]);

        $assignment->update(['exam_room_id' => $validated['exam_room_id'] ?? null]);

        return back()->with('success', 'Room assignment updated.');
    }

    public function destroy(ExamAssignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    /**
     * Admin override: change WHERE (venue) and WHAT ROLE a member is
     * assigned to, without resetting the confirmation pipeline — the
     * director's approval and the member's confirmation are treated as
     * still valid; this only corrects the logistics. Ported from the
     * legacy force-reassign.php. The room is always cleared since it was
     * tied to the old role+venue combination — re-run staffing randomization
     * afterward.
     */
    public function forceReassign(Request $request, ExamAssignment $assignment): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();
        $assignment->loadMissing('examination', 'member');

        abort_if(
            $user->role->isFieldOfficeScoped()
                && ! in_array($assignment->testing_center_id, $user->scopedTestingCenterIds(), true),
            403,
        );

        abort_if(
            $assignment->examination->exam_date->isPast(),
            422,
            'Cannot reassign: this examination has already concluded.',
        );

        $validated = $request->validate([
            'role' => ['required', Rule::enum(ExamRole::class)],
            'examination_school_id' => [
                'required',
                Rule::exists('examination_school', 'id')->where('examination_id', $assignment->examination_id),
                $this->venueJurisdictionRule($request, [$assignment->member_id]),
            ],
        ]);

        $previous = ['role' => $assignment->role->value, 'examination_school_id' => $assignment->examination_school_id];

        DB::transaction(function () use ($assignment, $validated, $user, $previous) {
            $assignment->update([
                'role' => $validated['role'],
                'examination_school_id' => $validated['examination_school_id'],
                'exam_room_id' => null,
            ]);

            if (! ExamRole::from($validated['role'])->isCoverageRole()) {
                $assignment->coveredSchools()->sync([]);
            }

            $assignment->confirmations()->create([
                'action' => ConfirmationAction::AdminOverride,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'override_by' => $user->id,
                    'previous' => $previous,
                    'new' => ['role' => $validated['role'], 'examination_school_id' => $validated['examination_school_id']],
                    'status_preserved' => $assignment->status->value,
                ],
            ]);
        });

        return back()->with('success', "{$assignment->member->name} reassigned. Confirmation status was preserved — re-run room staffing for the affected venue(s).");
    }

    /**
     * Superadmin-only bulk override: revoke assignments regardless of their
     * confirmation status (bypasses the normal delete authorization, which
     * legacy called the "pipeline-lock guard"). Ported from revoke-designation.php.
     */
    public function bulkRevoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::SuperAdmin, 403);

        $validated = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', Rule::exists('exam_assignments', 'id')],
        ]);

        $assignments = ExamAssignment::with('member:id,name,proctad_id')
            ->whereIn('id', $validated['assignment_ids'])
            ->get();

        $revoked = $assignments->map(fn (ExamAssignment $a) => [
            'assignment_id' => $a->id,
            'member' => $a->member?->proctad_id,
            'role' => $a->role->value,
            'status' => $a->status->value,
        ]);

        ExamAssignment::whereIn('id', $validated['assignment_ids'])->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'designation_revoked',
            'auditable_type' => ExamAssignment::class,
            'auditable_id' => $assignments->first()->id,
            'field_office_id' => null,
            'changes' => ['revoked_count' => $revoked->count(), 'revoked' => $revoked],
        ]);

        return back()->with('success', "Revoked {$revoked->count()} designation(s).");
    }

    /**
     * Request a Designation Order for this assignment — queued for approval by
     * the Field Director of the concerned Field Office (spec 2.3).
     */
    public function requestDesignationOrder(
        Request $request,
        ExamAssignment $assignment,
        \App\Services\CertificateService $certificates,
    ): RedirectResponse {
        Gate::authorize('update', $assignment);

        $certificate = $certificates->generatePending(
            \App\Enums\CertificateType::DesignationOrder,
            $assignment,
            $request->user(),
        );

        return back()->with('success', $certificate->wasRecentlyCreated
            ? 'Designation Order queued for '.UserRole::FieldDirector->label().' approval.'
            : 'A Designation Order request already exists for this assignment.');
    }

    /**
     * REC/LEC/CE-for-Investigation assignments reference multiple schools
     * they're responsible for monitoring (distinct from their one testing
     * center — see ExamAssignment::coveredSchools()). Non-coverage roles
     * never carry this list, so it's cleared rather than silently retained
     * if a role is edited away from a coverage role.
     */
    private function syncCoveredSchools(ExamAssignment $assignment, array $schoolIds): void
    {
        $assignment->coveredSchools()->sync($assignment->isCoverageRole() ? $schoolIds : []);
    }
}
