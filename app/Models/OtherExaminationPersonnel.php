<?php

namespace App\Models;

use App\Enums\PersonnelType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'oep_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex',
    'contact_number', 'email', 'agency', 'position', 'personnel_type',
    'field_office_id', 'photo_path', 'is_active', 'created_by',
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

    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->fullName());
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

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
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

    public function fullName(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' '));
    }
}
