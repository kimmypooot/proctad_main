<?php

namespace App\Models;

use App\Enums\PersonnelType;
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
    'oep_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex',
    'contact_number', 'email', 'agency', 'position', 'personnel_type',
    'field_office_id', 'testing_center_id', 'photo_path', 'is_active', 'created_by',
])]
class OtherExaminationPersonnel extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'other_examination_personnel';

    public const ID_PREFIX = 'OEP-CSCRO8-';

    /** Unambiguous charset: A-Z without I/O, digits without 0/1 (matches Member). */
    private const ID_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'personnel_type' => PersonnelType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $oep) {
            $oep->oep_id ??= self::generateOepId();
        });
    }

    public static function generateOepId(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ID_CHARSET[random_int(0, strlen(self::ID_CHARSET) - 1)];
            }
            $id = self::ID_PREFIX.$code;
        } while (self::withTrashed()->where('oep_id', $id)->exists());

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
     * OEP records are recorded in uppercase throughout (ID cards, rosters),
     * matching Member — normalize on write so it's automatic for new and
     * edited records.
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

    /** Employer and post, uppercased for the same reason as Member's. */
    protected function agency(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    protected function position(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value !== null ? mb_strtoupper($value) : null);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    /**
     * The testing center (city) this person works in — their jurisdiction, and
     * what decides which staff may see and manage them, exactly as for members.
     * Null only for regional-office personnel, who serve region-wide.
     */
    public function testingCenter(): BelongsTo
    {
        return $this->belongsTo(TestingCenter::class);
    }

    /**
     * Whether this person serves region-wide rather than out of one field
     * office — true for regional-office (RO8) personnel, who may be assigned
     * to any venue in the region.
     *
     * Region-wide is the regional office itself, never a missing office: a null
     * `field_office_id` used to be read as region-wide (legacy
     * `proctad_non_exam_personnel.field_office_id`, "NULL = region-wide") but
     * nothing ever implemented that, so those rows were invisible to every
     * field office instead. The column is now required and backfilled — see
     * 2026_07_26_000003_require_field_office_on_other_examination_personnel.
     *
     * Prefers an already-loaded relation, matching Member::isRegionWide():
     * this is called per row while presenting lists.
     */
    public function isRegionWide(): bool
    {
        return (bool) ($this->relationLoaded('fieldOffice')
            ? $this->fieldOffice?->is_regional
            : $this->fieldOffice()->value('is_regional'));
    }

    /**
     * Personnel a field-office-scoped user may see and manage: those working in
     * a testing center their office covers, plus regional-office personnel, who
     * serve region-wide and so belong to every office's pool.
     *
     * Scoped by center rather than by office for the same reason members are:
     * Leyte I and Leyte II both serve Tacloban City, and staff of either must
     * see the personnel working there whichever office hired them.
     *
     * The single definition of "within my jurisdiction" for OEP queries — used
     * by the list, the scanner lookup, and assignment, which must agree or
     * staff see people they cannot act on.
     */
    public function scopeWithinJurisdictionOf(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('testing_center_id', $user->scopedTestingCenterIds())
            ->orWhereHas('fieldOffice', fn (Builder $o) => $o->where('is_regional', true)));
    }

    /**
     * Row-level form of scopeWithinJurisdictionOf, for guards on a single
     * record. Named differently from the scope for the same reason as
     * Member::isWithinJurisdictionOf.
     */
    public function isWithinJurisdictionOf(User $user): bool
    {
        return $this->isRegionWide()
            || ($this->testing_center_id !== null
                && in_array($this->testing_center_id, $user->scopedTestingCenterIds(), true));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OepAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(OepAttendance::class);
    }

    /**
     * "First Middle Last Suffix" — kept separate from the `name` accessor
     * above for the few contexts that must not change format: ID cards and
     * email greetings.
     */
    public function fullName(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' '));
    }
}
