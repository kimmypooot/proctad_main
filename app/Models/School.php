<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'testing_center_id', 'name',
    'contact_person', 'contact_number', 'contact_email', 'is_active',
])]
class School extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function testingCenter(): BelongsTo
    {
        return $this->belongsTo(TestingCenter::class);
    }

    public function examinationSchools(): HasMany
    {
        return $this->hasMany(ExaminationSchool::class);
    }

    /** Schools whose testing center is handled by the given field office. */
    public function scopeForFieldOffice(Builder $query, ?int $fieldOfficeId): Builder
    {
        if ($fieldOfficeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('testingCenter.fieldOffices', fn (Builder $q) => $q->whereKey($fieldOfficeId));
    }

    /** The field offices that handle this school's testing center. */
    public function handlingFieldOfficeIds(): array
    {
        return $this->testingCenter
            ? $this->testingCenter->fieldOffices()->pluck('field_offices.id')->all()
            : [];
    }

    public function handledByOffice(?int $fieldOfficeId): bool
    {
        return $fieldOfficeId !== null && in_array($fieldOfficeId, $this->handlingFieldOfficeIds(), true);
    }
}
