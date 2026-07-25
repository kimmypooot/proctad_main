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
     * The office that currently owns intake for this center. Only matters where
     * a center is shared (Tacloban City, handled by Leyte I and Leyte II in
     * turn) — registration has to resolve to exactly one office, and this is it.
     * Falls back to any handling office so a center whose primary flag was never
     * set still accepts registrations.
     */
    public function primaryFieldOfficeId(): ?int
    {
        return $this->fieldOffices()->wherePivot('is_primary', true)->value('field_offices.id')
            ?? $this->fieldOffices()->orderBy('field_offices.id')->value('field_offices.id');
    }

    /**
     * Hand intake for this center to one of its handling offices — how a
     * hosting rotation is recorded (August 2026 Leyte I, March 2027 Leyte II,
     * or the same office twice running when management decides so).
     *
     * Exactly one office may be primary, so the flag is cleared across the
     * center and re-set in one transaction; a half-applied change would leave
     * registrations resolving to two offices or none.
     *
     * Audited by hand: the pivot is written through the query builder, which
     * fires no model events, and who receives new registrants is exactly the
     * kind of change the audit trail exists for.
     */
    public function designatePrimaryFieldOffice(int $fieldOfficeId): void
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
            'old' => ['primary_field_office_id' => $previousId],
            'new' => ['primary_field_office_id' => $fieldOfficeId],
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
