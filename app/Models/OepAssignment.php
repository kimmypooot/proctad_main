<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['other_examination_personnel_id', 'examination_school_id', 'status', 'assigned_by'])]
class OepAssignment extends Model
{
    use Auditable, HasFactory;

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(OtherExaminationPersonnel::class, 'other_examination_personnel_id');
    }

    public function examinationSchool(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchool::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
