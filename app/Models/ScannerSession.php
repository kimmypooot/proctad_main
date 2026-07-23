<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A short-lived, revocable link that lets venue staff open the QR scanner on
 * any phone without logging in. The session is pinned to one examination (and
 * optionally one venue) or one training, and carries the issuing user so
 * attendance writes stay attributable and field-office scoping survives.
 */
#[Fillable([
    'token', 'label', 'examination_id', 'training_id', 'examination_school_id',
    'field_office_id', 'created_by', 'expires_at', 'revoked_at',
])]
class ScannerSession extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * 40 random characters — the link is the only credential, so it has to be
     * infeasible to guess rather than merely unlikely to collide.
     */
    public static function generateToken(): string
    {
        return Str::random(40);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @param  Builder<ScannerSession>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function examinationSchool(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchool::class);
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }
}
