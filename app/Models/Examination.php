<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'type', 'exam_type_id', 'exam_date'])]
class Examination extends Model
{
    /** @use HasFactory<\Database\Factories\ExaminationFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /** Venue records (examination_school rows) for this examination. */
    public function venues(): HasMany
    {
        return $this->hasMany(ExaminationSchool::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'examination_school')
            ->withPivot(['id', 'assigned_by', 'is_active'])
            ->withTimestamps();
    }
}
