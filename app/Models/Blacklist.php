<?php

namespace App\Models;

use App\Enums\BlacklistStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedByTestingCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id', 'field_office_id', 'testing_center_id', 'reason', 'status',
    'blacklisted_by', 'blacklisted_at', 'lifted_by', 'lifted_at', 'lift_reason',
])]
class Blacklist extends Model
{
    /** @use HasFactory<\Database\Factories\BlacklistFactory> */
    use Auditable, HasFactory, ScopedByTestingCenter;

    protected function casts(): array
    {
        return [
            'status' => BlacklistStatus::class,
            'blacklisted_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    public function blacklistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blacklisted_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }
}
