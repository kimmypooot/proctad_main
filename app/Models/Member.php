<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'date_of_birth', 'email',
    'mobile_number', 'agency', 'position', 'photo_path', 'field_office_id', 'testing_center_id',
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
            // PII (RA 10173) — encrypted at rest; stored/read as a plain
            // 'Y-m-d' string, not a Carbon instance.
            'date_of_birth' => 'encrypted',
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

    /** Default display format: "Last Name, First Name Middle Name Suffix". */
    protected function name(): Attribute
    {
        return Attribute::get(function () {
            $given = trim(collect([$this->first_name, $this->middle_name, $this->suffix])->filter()->implode(' '));

            return trim(collect([$this->last_name, $given])->filter()->implode(', '));
        });
    }

    /**
     * "First Middle Last Suffix" — kept separate from the `name` accessor
     * above for the few contexts that must not change format: certificates
     * (which already have their own per-type formatting in
     * CertificateService), ID cards, and email greetings.
     */
    public function nameFirstLast(): string
    {
        return trim(collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->implode(' '));
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

    /**
     * The accredited member who holds a given office — used for the ex officio
     * REC seats (see ExamRole::reservedForRole).
     *
     * Null when the post is vacant *or* when its holder has not been enrolled
     * in the registry yet. Both are the same thing here: an official who is not
     * an accredited member cannot be assigned to an examination at all, so the
     * seat has no rightful claimant and falls open to the pool.
     */
    public static function holdingOffice(UserRole $role): ?self
    {
        return static::whereHas('user', fn ($q) => $q->where('role', $role)->where('is_active', true))
            ->first();
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    /**
     * The testing center (city) this member serves — their jurisdiction, and
     * what decides which staff may see and manage them. Null for regional-office
     * members, who serve region-wide, and for the handful of members whose
     * office handles several centers and who have not been placed in one yet.
     */
    public function testingCenter(): BelongsTo
    {
        return $this->belongsTo(TestingCenter::class);
    }

    /**
     * The office administering this member for signing purposes — whose
     * signatory appears on their ID card and certificates.
     *
     * CSC staff are administered by the office they work for. External test
     * administrators have no office of their own, so it comes from the office
     * that administers their testing center.
     */
    public function administeringFieldOfficeId(): ?int
    {
        return $this->field_office_id
            ?? $this->testingCenter?->administeringFieldOfficeId();
    }

    /**
     * Whether this member may be assigned to any venue in the region rather
     * than only their own testing center.
     *
     * Read from their staff account, not from the member record: a field office
     * describes who someone works for, which is a fact about CSC employees.
     * External test administrators work for their own agency and have none.
     *
     * Two ways to qualify, because region-wide staff are recorded both ways.
     * ESD Admins and the directors hold no field office at all — their role is
     * already region-wide, so none was ever needed — while other regional
     * office employees are placed there by office.
     */
    public function isRegionWide(): bool
    {
        return $this->user !== null
            && ($this->user->role->isRegionWide() || (bool) $this->user->fieldOffice?->is_regional);
    }

    /** The SQL form of isRegionWide(), for use inside a query. */
    private static function regionWideConstraint(): \Closure
    {
        return fn (Builder $query) => $query->whereHas('user', fn (Builder $u) => $u
            ->whereIn('role', UserRole::regionWideValues())
            ->orWhereHas('fieldOffice', fn (Builder $o) => $o->where('is_regional', true)));
    }

    /** Members whose staff account gives them region-wide reach. */
    public function scopeServingRegionWide(Builder $query): Builder
    {
        return $query->where(self::regionWideConstraint());
    }

    /** The complement: members confined to the testing center they serve. */
    public function scopeNotServingRegionWide(Builder $query): Builder
    {
        return $query->whereNot(self::regionWideConstraint());
    }

    /**
     * Members a field-office-scoped user may draw on: those serving a testing
     * center their office covers, plus regional-office members, who serve
     * region-wide and so belong to every office's pool.
     *
     * The single definition of "within my jurisdiction" for member queries —
     * used by the members list, the assignment candidate pool, and the bulk
     * assignment filter, which must agree or staff see people they cannot act on.
     */
    public function scopeWithinJurisdictionOf(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('testing_center_id', $user->scopedTestingCenterIds())
            ->orWhere(self::regionWideConstraint()));
    }

    /**
     * Row-level form of scopeWithinJurisdictionOf, for guards on a single
     * member. Named differently from the scope on purpose: an instance method
     * called `withinJurisdictionOf` would shadow the scope for any static-style
     * call, and the two would silently diverge.
     */
    public function isWithinJurisdictionOf(User $user): bool
    {
        return $this->isRegionWide()
            || ($this->testing_center_id !== null
                && in_array($this->testing_center_id, $user->scopedTestingCenterIds(), true));
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

    public function blacklists(): HasMany
    {
        return $this->hasMany(Blacklist::class);
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
