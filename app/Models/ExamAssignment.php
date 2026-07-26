<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedByTestingCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'examination_id', 'examination_school_id', 'exam_room_id', 'member_id', 'role', 'field_office_id', 'testing_center_id',
    'status', 'confirmation_sent_at', 'responded_at', 'decline_reason',
    'attendance_confirmed_at', 'attendance_confirmed_by', 'performance_rating', 'remarks',
    'marked_absent_at', 'marked_absent_by', 'covering_for_assignment_id', 'original_role',
])]
class ExamAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\ExamAssignmentFactory> */
    use Auditable, HasFactory, ScopedByTestingCenter;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'role' => ExamRole::class,
            'status' => AssignmentStatus::class,
            'performance_rating' => PerformanceRating::class,
            'confirmation_sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'attendance_confirmed_at' => 'datetime',
            'marked_absent_at' => 'datetime',
            'original_role' => ExamRole::class,
        ];
    }

    /** The seat this alternate was called in to cover, if any. */
    public function coveringFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'covering_for_assignment_id');
    }

    /** The alternate who took over this seat, if one was activated. */
    public function coveredBy(): HasOne
    {
        return $this->hasOne(self::class, 'covering_for_assignment_id');
    }

    public function markedAbsentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_absent_by');
    }

    /** Did not report on exam day — distinct from simply not yet scanned. */
    public function isAbsent(): bool
    {
        return $this->marked_absent_at !== null;
    }

    /** Called in from the standby pool to fill a seat left vacant. */
    public function isSubstitute(): bool
    {
        return $this->covering_for_assignment_id !== null;
    }

    /**
     * How this service ended, in three states rather than the two the record
     * used to carry. "Not recorded" and "Absent" were previously the same
     * empty timestamp, which read on a member's own history as though the
     * office had simply forgotten to scan them.
     */
    public function attendanceOutcome(): string
    {
        return match (true) {
            $this->attendance_confirmed_at !== null => 'Present',
            $this->isAbsent() => 'Absent',
            default => 'Not recorded',
        };
    }

    /**
     * The exam-day story behind this row, when there is one — so a substitution
     * reads as service rendered rather than as a role someone was mysteriously
     * assigned, and a no-show is not mistaken for a clerical gap. Null for an
     * ordinary assignment served ordinarily.
     */
    public function serviceNote(): ?string
    {
        if ($this->isSubstitute()) {
            $covered = $this->coveringFor?->member?->name;

            return $covered === null
                ? 'Served as Alternate Examiner, covering a vacant seat.'
                : "Served as Alternate Examiner, covering for {$covered}.";
        }

        if ($this->isAbsent()) {
            $substitute = $this->coveredBy?->member?->name;

            return $substitute === null
                ? 'Did not report on exam day.'
                : "Did not report on exam day; covered by {$substitute}.";
        }

        return null;
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function examinationSchool(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchool::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_confirmed_by');
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(AssignmentConfirmation::class);
    }

    /**
     * Schools this REC/LEC/CE-for-Investigation assignment is responsible
     * for monitoring, distinct from examinationSchool() (their one testing
     * center / duty station). Pre-determined, no confirmation workflow —
     * see attendances() for per-school attendance tracking.
     */
    public function coveredSchools(): BelongsToMany
    {
        return $this->belongsToMany(ExaminationSchool::class, 'exam_assignment_schools')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ExamAssignmentAttendance::class);
    }

    /**
     * This assignment's own submitted Post-Examination Evaluation, if any.
     * No uniqueness is enforced on Evaluation.exam_assignment_id, so
     * latestOfMany() picks the most recent submission deterministically
     * rather than an arbitrary row.
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class, 'exam_assignment_id')->latestOfMany();
    }

    public function isCoverageRole(): bool
    {
        return $this->role->isCoverageRole();
    }

    /**
     * Assignments that owe a post-examination evaluation at all — a covered
     * designation, and attendance actually confirmed.
     *
     * Attendance is the part that is easy to omit and expensive to get wrong.
     * EvaluationController::resolve() refuses an unconfirmed assignment, so
     * anything listed without this filter is someone the system will not let
     * evaluate: staff chase them, and the member is told there is nothing to do.
     *
     * @param  Builder<ExamAssignment>  $query
     */
    public function scopeEvaluable(Builder $query): void
    {
        $query->whereIn('role', array_column(ExamRole::evaluableCases(), 'value'))
            ->whereNotNull('attendance_confirmed_at');
    }

    /**
     * Assignments a member has served but not yet evaluated — what the member
     * dashboard, service history and the form's own shortcut all read.
     *
     * @param  Builder<ExamAssignment>  $query
     */
    public function scopeAwaitingEvaluationFor(Builder $query, int $memberId): void
    {
        $query->evaluable()
            ->where('member_id', $memberId)
            ->whereDoesntHave('evaluation');
    }
}
