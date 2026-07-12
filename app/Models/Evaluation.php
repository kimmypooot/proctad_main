<?php

namespace App\Models;

use App\Enums\ExamRole;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'examination_id', 'exam_assignment_id',
    'respondent_name', 'designation', 'field_office_id', 'school_id',
    'room_ratings', 'room_readiness', 'exam_preparation',
    'venue_readiness', 'venue_comment',
    'committee_coordination', 'committee_comment',
    'conduct_of_exam', 'conduct_comment',
    'examinee_experience', 'examinee_comment',
    'overall_rating', 'what_worked', 'challenges', 'improvements', 'suggestions',
])]
class Evaluation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'designation' => ExamRole::class,
            'room_ratings' => 'array',
            'room_readiness' => 'array',
            'exam_preparation' => 'array',
            'venue_readiness' => 'array',
            'committee_coordination' => 'array',
            'conduct_of_exam' => 'array',
            'examinee_experience' => 'array',
            'overall_rating' => 'integer',
        ];
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }
}
