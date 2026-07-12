<?php

namespace App\Models;

use App\Enums\TrainingType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'type', 'training_date', 'end_date', 'venue', 'completed_at'])]
class Training extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => TrainingType::class,
            'training_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }
}
