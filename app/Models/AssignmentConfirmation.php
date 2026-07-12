<?php

namespace App\Models;

use App\Enums\ConfirmationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of assignment confirmation events.
 */
#[Fillable(['exam_assignment_id', 'action', 'ip_address', 'user_agent', 'metadata'])]
class AssignmentConfirmation extends Model
{
    use HasFactory;

    public const ?string UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ConfirmationAction::class,
            'metadata' => 'array',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }
}
