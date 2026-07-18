<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'certificate_no', 'type', 'member_id', 'field_office_id',
    'certifiable_type', 'certifiable_id', 'status', 'requested_by',
    'approved_by', 'approved_at', 'disapproval_remarks',
    'signatory_name', 'signatory_position', 'pdf_path', 'released_at',
])]
class Certificate extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
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

    public function certifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Human-readable description of the event this certificate stems from.
     */
    public function sourceDescription(): string
    {
        $source = $this->certifiable;

        return match (true) {
            $source instanceof ExamAssignment => ($source->examination?->title ?? 'Examination')
                .' — '.$source->role->label(),
            $source instanceof TrainingAssignment => $source->training?->title ?? 'Training',
            default => '—',
        };
    }

    public function sourceDate(): ?string
    {
        return $this->sourceDateRaw()?->format('M d, Y');
    }

    /**
     * The raw date of the source event (exam or training), for callers that
     * need to format it themselves — e.g. the certificate's "Issued this Nth
     * of Month Year" line, which needs the ordinal-day format rather than the
     * 'M d, Y' string sourceDate() returns.
     */
    public function sourceDateRaw(): ?\Illuminate\Support\Carbon
    {
        $source = $this->certifiable;

        return match (true) {
            $source instanceof ExamAssignment => $source->examination?->exam_date,
            $source instanceof TrainingAssignment => $source->training?->training_date,
            default => null,
        };
    }
}
