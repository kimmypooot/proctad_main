<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'non_exam_personnel_id', 'examination_school_id', 'status', 'scan_method', 'scanned_at', 'scanned_by',
])]
class NepAttendance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(NonExamPersonnel::class, 'non_exam_personnel_id');
    }

    public function examinationSchool(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchool::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
