<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school serving as a venue for a specific examination.
 */
#[Fillable(['examination_id', 'school_id', 'assigned_by', 'is_active'])]
class ExaminationSchool extends Model
{
    use Auditable, HasFactory;

    protected $table = 'examination_school';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ExamRoom::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    public function nepAssignments(): HasMany
    {
        return $this->hasMany(NepAssignment::class);
    }
}
