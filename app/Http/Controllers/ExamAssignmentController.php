<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\BlacklistStatus;
use App\Enums\ConfirmationAction;
use App\Enums\ExamRole;
use App\Enums\MemberStatus;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Blacklist;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\Member;
use App\Models\User;
use App\Services\AssignmentConfirmationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExamAssignmentController extends Controller
{
    // Proctor/Room Examiner/Supervising Examiner physically staff one specific
    // school, so (unlike REC/LEC committee roles, which are region-wide by
    // design) they must always be drawn from that venue's own field office.
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

            $venueFieldOfficeId = ExaminationSchool::find($value)?->school?->field_office_id;
            if ($venueFieldOfficeId === null) {
                return;
            }

            $outOfJurisdiction = Member::whereIn('id', array_filter($memberIds))
                ->where('field_office_id', '!=', $venueFieldOfficeId)
                ->exists();

            if ($outOfJurisdiction) {
                $fail('Proctor, Room Examiner, and Supervising Examiner assignments must stay within the venue\'s own field office.');
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
            'covered_school_ids' => ['nullable', 'array'],
            'covered_school_ids.*' => [
                'integer',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
            ],
        ], [
            'member_id.unique' => 'This member is already assigned to this examination.',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        // FO Admins may only assign members of their own Testing Center.
        abort_if(
            $user->role->isFieldOfficeScoped() && $member->field_office_id !== $user->field_office_id,
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
    public function bulkStore(Request $request, Examination $examination, AssignmentConfirmationSender $confirmationSender): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', Rule::exists('members', 'id')],
            'role' => ['required', Rule::enum(ExamRole::class)],
            'examination_school_id' => [
                'nullable',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
                $this->venueJurisdictionRule($request, $request->input('member_ids', [])),
            ],
            'covered_school_ids' => ['nullable', 'array'],
            'covered_school_ids.*' => [
                'integer',
                Rule::exists('examination_school', 'id')->where('examination_id', $examination->id),
            ],
        ]);

        $alreadyAssigned = $examination->assignments()->pluck('member_id')->all();
        $members = Member::query()
            ->whereIn('id', $validated['member_ids'])
            ->where('status', 'active')
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
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
                'examination_school_id' => $validated['examination_school_id'] ?? null,
            ]);
            $this->syncCoveredSchools($assignment, $validated['covered_school_ids'] ?? []);

            // A venue is enough for the member to need to know and confirm —
            // notify them (email + in-app) the moment one is set, rather than
            // waiting on staff to remember to click "Send Confirmation".
            if ($assignment->examination_school_id) {
                $confirmationSender->send($assignment, $user);
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
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'status', 'field_office_id'])
            ->keyBy('id');

        $descriptions = collect($skippedIds)->map(function ($id) use ($skippedMembers, $alreadyAssigned, $blacklistedIds, $user) {
            $member = $skippedMembers->get($id);
            $name = $member?->name ?? "member #{$id}";

            $reason = match (true) {
                in_array($id, $alreadyAssigned, true) => 'already assigned',
                in_array($id, $blacklistedIds, true) => 'blacklisted',
                $member === null => 'not found',
                $member->status !== MemberStatus::Active => 'inactive',
                $user->role->isFieldOfficeScoped() && $member->field_office_id !== $user->field_office_id => 'different field office',
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
     */
    public function bulkConfirm(Request $request): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', Rule::exists('exam_assignments', 'id')],
        ]);

        $assignments = ExamAssignment::whereIn('id', $validated['assignment_ids'])
            ->where('status', AssignmentStatus::Pending)
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['status' => AssignmentStatus::Confirmed, 'responded_at' => now()]);
        }

        return back()->with('success', "Confirmed {$assignments->count()} assignment(s).");
    }

    public function update(Request $request, ExamAssignment $assignment, AssignmentConfirmationSender $confirmationSender): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $hadVenue = $assignment->examination_school_id !== null;

        $validated = $request->validate([
            'role' => ['required', Rule::enum(ExamRole::class)],
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
            'covered_school_ids' => ['nullable', 'array'],
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
        // checks the assignee's own field office. That normally matches the
        // venue's via venueJurisdictionRule, but isn't guaranteed for every
        // row (e.g. legacy data), and a venue's FO admin must always be able
        // to room-assign whoever the venue's own unassigned pool shows them.
        $assignment->loadMissing('examinationSchool.school');
        $venueFieldOfficeId = $assignment->examinationSchool?->school?->field_office_id;

        abort_unless(
            $request->user()->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)
                || ($request->user()->role->isFieldOfficeScoped() && $request->user()->field_office_id === $venueFieldOfficeId),
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
            $user->role->isFieldOfficeScoped() && $assignment->field_office_id !== $user->field_office_id,
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
     * the Field Director of the concerned Testing Center (spec 2.3).
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
            ? 'Designation Order queued for Field Director approval.'
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
