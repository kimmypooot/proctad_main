<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\RoomStaffingCalculator;
use App\Support\DesignationRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school serving as a venue for a specific examination.
 */
#[Fillable(['examination_id', 'school_id', 'assigned_by', 'is_active', 'rooms_per_supervisor'])]
class ExaminationSchool extends Model
{
    use Auditable, HasFactory;

    /**
     * A Supervising Examiner covers between 3 and 8 rooms — the field office
     * decides where in that range a given venue sits, based on its layout.
     */
    public const MIN_ROOMS_PER_SUPERVISOR = 3;

    public const MAX_ROOMS_PER_SUPERVISOR = 8;

    protected $table = 'examination_school';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rooms_per_supervisor' => 'integer',
        ];
    }

    /**
     * This venue's group size, falling back to the Supervising Examiner
     * designation's default for venues never staffed since the value became
     * configurable.
     */
    public function roomsPerSupervisor(): int
    {
        return $this->rooms_per_supervisor
            ?? collect(DesignationRegistry::roomDesignations())
                ->firstWhere('is_anchored', true)['rooms_per_slot']
            ?? RoomStaffingCalculator::ROOMS_PER_SUPERVISOR;
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

    public function oepAssignments(): HasMany
    {
        return $this->hasMany(OepAssignment::class);
    }
}
