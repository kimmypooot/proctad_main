<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A testing center (city, e.g. "Tacloban City"). A center can be handled by
 * several field offices at once (they take turns hosting), so ownership is a
 * many-to-many link rather than a single owner. Schools sit under a center and
 * are therefore shared by every field office that handles it.
 */
#[Fillable(['name', 'is_active'])]
class TestingCenter extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function fieldOffices(): BelongsToMany
    {
        return $this->belongsToMany(FieldOffice::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * The office administering this center. Members are not filed under an
     * office any more, so this no longer decides where a registrant lands — it
     * decides whose signatory signs the ID cards and certificates of the
     * members who serve here, and rotates when administration does.
     *
     * The fallback skips regional offices: they sit alongside every center for
     * oversight, and letting one win by id order would put the regional
     * signatory on certificates belonging to a field office's members.
     */
    public function administeringFieldOfficeId(): ?int
    {
        return $this->fieldOffices()->wherePivot('is_primary', true)->value('field_offices.id')
            ?? $this->fieldOffices()
                ->where('is_regional', false)
                ->orderBy('field_offices.id')
                ->value('field_offices.id');
    }

    /**
     * Hand administration of this center to one of its handling offices.
     *
     * Exactly one office may hold it, so the flag is cleared across the center
     * and re-set in one transaction; a half-applied change would leave two
     * offices claiming it or none.
     *
     * Audited by hand: the pivot is written through the query builder, which
     * fires no model events, and whose signature appears on a member's
     * certificate is the kind of change the audit trail exists for.
     */
    public function designateAdministeringFieldOffice(int $fieldOfficeId): void
    {
        $previousId = $this->fieldOffices()->wherePivot('is_primary', true)->value('field_offices.id');

        if ($previousId === $fieldOfficeId) {
            return;
        }

        DB::transaction(function () use ($fieldOfficeId) {
            DB::table('field_office_testing_center')
                ->where('testing_center_id', $this->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            DB::table('field_office_testing_center')
                ->where('testing_center_id', $this->id)
                ->where('field_office_id', $fieldOfficeId)
                ->update(['is_primary' => true, 'updated_at' => now()]);
        });

        $this->recordAudit('updated', [
            'old' => ['administering_field_office_id' => $previousId],
            'new' => ['administering_field_office_id' => $fieldOfficeId],
        ]);
    }

    /** Staff accounts that operate at this center (see testing_center_user). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    /** Centers handled by the given field office (none when the office is null). */
    public function scopeForFieldOffice(Builder $query, ?int $fieldOfficeId): Builder
    {
        if ($fieldOfficeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('fieldOffices', fn (Builder $q) => $q->whereKey($fieldOfficeId));
    }
}
