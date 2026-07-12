<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_id', 'member_id', 'field_office_id',
    'attendance_confirmed_at', 'attendance_confirmed_by',
])]
class TrainingAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingAssignmentFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'attendance_confirmed_at' => 'datetime',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }
}
