<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An examination duty or personnel type that can be assigned.
 *
 * `key` is what gets written to exam_assignments.role and
 * other_examination_personnel.personnel_type, and never changes once created —
 * renaming or re-filing a designation must not rewrite history.
 *
 * Built-in rows correspond to a case of ExamRole or PersonnelType and cannot be
 * deleted, because the payroll workbook, room grid, evaluation form and ex
 * officio committee seats all name them directly.
 */
#[Fillable(['section', 'key', 'label', 'designation_category_id', 'is_active', 'rooms_per_slot', 'sort_order'])]
class Designation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_builtin' => 'boolean',
            'sort_order' => 'integer',
            'rooms_per_slot' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignationCategory::class, 'designation_category_id');
    }

    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
