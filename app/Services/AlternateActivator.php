<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exam-day cover: marking a test administrator absent, and calling an Alternate
 * Examiner in from the venue's standby pool to take the vacant seat.
 *
 * Single source of truth for both, because two front doors reach it — the
 * venue scanner on the day and the admin console afterward — and the rules
 * must not drift between them.
 *
 * Activation rewrites the alternate's own designation to the seat they took
 * over. That is deliberate: the certificate prints the assignment's role
 * (CertificateService), and ExamRole::evaluableCases() decides who the
 * post-exam evaluation covers. Leaving them as 'alternate_examiner' would
 * hand a full day's proctoring a certificate that misstates it and a room
 * that produces no evaluation.
 */
class AlternateActivator
{
    /**
     * Reasons an activation can be refused, as user-facing sentences. Null on
     * success. Returned rather than thrown: both callers turn these into a
     * flash message, and none of them is exceptional — they are ordinary
     * outcomes of two people acting on the same venue at once.
     */
    public function cannotActivate(ExamAssignment $alternate, ExamAssignment $vacant): ?string
    {
        if (! $alternate->role->is(ExamRole::AlternateExaminer)) {
            return 'Only an Alternate Examiner can be called in to cover a seat.';
        }

        // Only room-floor seats (Supervising Examiner, Room Examiner, Proctor)
        // are covered from a venue's standby pool. REC/LEC and other committee
        // roles staff a field office or the region and cannot be filled by an
        // alternate standing in one venue.
        if (! $vacant->role->isCoverable()) {
            return 'Only a Supervising Examiner, Room Examiner, or Proctor seat can be covered by an alternate.';
        }

        if ($alternate->is($vacant)) {
            return 'An assignment cannot cover for itself.';
        }

        if ($alternate->examination_id !== $vacant->examination_id) {
            return 'The alternate and the vacant seat belong to different examinations.';
        }

        // The standby pool is per venue: an alternate on duty at one testing
        // center cannot cover a room they are not physically at.
        if ($alternate->examination_school_id !== $vacant->examination_school_id) {
            return 'An alternate can only cover a seat at the venue they are assigned to.';
        }

        if (! $vacant->isAbsent()) {
            return 'Mark the assigned test administrator absent before calling in an alternate.';
        }

        if ($vacant->isSubstitute()) {
            return 'This seat is itself a substitution and cannot be covered again.';
        }

        if ($vacant->coveredBy()->exists()) {
            return 'Another alternate has already been called in for this seat.';
        }

        if ($alternate->isSubstitute()) {
            return 'This alternate is already covering another seat.';
        }

        if ($alternate->isAbsent()) {
            return 'This alternate was marked absent and cannot be called in.';
        }

        return null;
    }

    /**
     * Record that an assignee did not report. Idempotent — two people pressing
     * the same button at a busy venue must not produce two different stamps.
     */
    public function markAbsent(ExamAssignment $assignment, User $by): bool
    {
        if ($assignment->isAbsent()) {
            return false;
        }

        // Someone already scanned in is present by definition; treating them as
        // a no-show would strand an alternate in a seat that is not vacant.
        if ($assignment->attendance_confirmed_at !== null) {
            return false;
        }

        $assignment->update([
            'marked_absent_at' => now(),
            'marked_absent_by' => $by->id,
        ]);

        return true;
    }

    /**
     * Undo an absence. Refused once an alternate has taken the seat — clearing
     * it then would leave two people holding one room.
     */
    public function clearAbsence(ExamAssignment $assignment): bool
    {
        if (! $assignment->isAbsent() || $assignment->coveredBy()->exists()) {
            return false;
        }

        $assignment->update(['marked_absent_at' => null, 'marked_absent_by' => null]);

        return true;
    }

    /**
     * Move an alternate into a vacant seat, inheriting its designation and room.
     */
    public function activate(ExamAssignment $alternate, ExamAssignment $vacant): void
    {
        DB::transaction(function () use ($alternate, $vacant) {
            $alternate->update([
                'original_role' => $alternate->role,
                'role' => $vacant->role,
                'exam_room_id' => $vacant->exam_room_id,
                'covering_for_assignment_id' => $vacant->id,
                // Whoever is stepping in is at the venue: the activation is
                // itself the report-in, and requiring a separate scan would
                // leave the room looking unstaffed all day.
                'attendance_confirmed_at' => $alternate->attendance_confirmed_at ?? now(),
                'status' => AssignmentStatus::Confirmed,
            ]);
        });
    }

    /**
     * Undo an activation, returning the alternate to the standby pool.
     */
    public function standDown(ExamAssignment $substitute): void
    {
        $substitute->update([
            'role' => $substitute->original_role ?? ExamRole::AlternateExaminer,
            'original_role' => null,
            'covering_for_assignment_id' => null,
            'exam_room_id' => null,
        ]);
    }

    /**
     * The venue's un-deployed standby pool for one assignment's examination.
     *
     * @return Collection<int, ExamAssignment>
     */
    public function availableAlternates(ExamAssignment $vacant): Collection
    {
        return ExamAssignment::query()
            ->where('examination_id', $vacant->examination_id)
            ->where('examination_school_id', $vacant->examination_school_id)
            ->where('role', ExamRole::AlternateExaminer)
            ->whereNull('covering_for_assignment_id')
            ->whereNull('marked_absent_at')
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
            ->get();
    }
}
