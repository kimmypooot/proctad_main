<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'examination_id', 'examination_school_id', 'exam_room_id', 'member_id', 'role', 'field_office_id',
    'status', 'confirmation_sent_at', 'responded_at', 'decline_reason',
    'attendance_confirmed_at', 'attendance_confirmed_by', 'performance_rating', 'remarks',
])]
class ExamAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\ExamAssignmentFactory> */
    use Auditable, HasFactory;

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
        ];
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
