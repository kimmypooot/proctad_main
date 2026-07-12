<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'email',
    'mobile_number', 'agency', 'position', 'photo_path', 'field_office_id',
    'status', 'disqualification_remarks', 'user_id',
])]
class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const ID_PREFIX = 'PROCTAD-CSCRO8-';

    /** Unambiguous charset: A-Z without I/O, digits without 0/1. */
    private const ID_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->proctad_id ??= self::generateProctadId();
        });
    }

    public static function generateProctadId(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ID_CHARSET[random_int(0, strlen(self::ID_CHARSET) - 1)];
            }
            $id = self::ID_PREFIX.$code;
        } while (self::withTrashed()->where('proctad_id', $id)->exists());

        return $id;
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim(collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->implode(' ')));
    }

    /**
     * PROCTAD members are recorded in uppercase throughout (ID cards,
     * certificates, rosters) — normalize on write so it's automatic for new
     * and edited records instead of relying on callers to remember it.
     */
    protected function firstName(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    protected function middleName(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    protected function lastName(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    protected function suffix(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(MemberRequirement::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /**
     * Service history is derived, not stored: attendance-confirmed exam assignments,
     * most recent examination first.
     */
    public function serviceHistory(): HasMany
    {
        return $this->hasMany(ExamAssignment::class)
            ->whereNotNull('attendance_confirmed_at')
            ->join('examinations', 'examinations.id', '=', 'exam_assignments.examination_id')
            ->orderByDesc('examinations.exam_date')
            ->select('exam_assignments.*');
    }

    public function trainingAssignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Latest attendance-confirmed assignment (the "last exam served").
     */
    public function lastServed(): ?ExamAssignment
    {
        return $this->assignments
            ->whereNotNull('attendance_confirmed_at')
            ->sortByDesc(fn (ExamAssignment $assignment) => $assignment->examination?->exam_date)
            ->first();
    }
}
