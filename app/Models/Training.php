<?php

namespace App\Models;

use App\Enums\TrainingSession;
use App\Enums\TrainingType;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'type', 'training_date', 'session', 'venue', 'completed_at', 'field_office_id', 'exam_id'])]
class Training extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => TrainingType::class,
            'training_date' => 'date',
            'session' => TrainingSession::class,
            'completed_at' => 'datetime',
            'field_office_id' => 'integer',
            'exam_id' => 'integer',
        ];
    }

    /**
     * A training with no field office is regional: organised by the region for
     * whoever it invites, rather than owned by one office. Every office sees
     * it, and none of them owns it — see scopeVisibleTo() and TrainingPolicy.
     */
    public function isRegional(): bool
    {
        return $this->field_office_id === null;
    }

    /**
     * Trainings this user may see: for a field office, its own — widened
     * through shared testing centers, as scopedFieldOfficeIds() does — plus
     * every regional one. Region-wide roles see all of them.
     *
     * @param  Builder<Training>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->isRegionWide()) {
            return;
        }

        $query->where(fn (Builder $scoped) => $scoped
            ->whereIn('field_office_id', $user->scopedFieldOfficeIds())
            ->orWhereNull('field_office_id'));
    }

    /**
     * The latest a public scanner link for this training may stay live — the
     * end of its sitting, since attendance is time-in only and a link that
     * outlives the sitting writes the next batch into this roster.
     */
    public function scannerLinkExpiry(): CarbonInterface
    {
        return $this->session->endsAt($this->training_date);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
