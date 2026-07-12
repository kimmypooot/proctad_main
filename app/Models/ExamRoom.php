<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['examination_school_id', 'room_number', 'capacity', 'designation'])]
class ExamRoom extends Model
{
    use Auditable, HasFactory;

    public function examinationSchool(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchool::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }
}
