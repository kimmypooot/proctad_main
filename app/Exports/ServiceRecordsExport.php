<?php

namespace App\Exports;

use App\Enums\PerformanceRating;
use App\Exports\Concerns\EscapesFormulas;
use App\Models\ExamAssignment;
use App\Services\PerformanceRatingCalculator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Service history / attendance export across members. `member_id` scopes to
 * a single member's service history (Phase 8's "per member" export); omit it
 * for the region/FO-wide service records export.
 */
class ServiceRecordsExport implements FromCollection, WithHeadings, WithMapping
{
    use EscapesFormulas;

    /** @var array<int, array{rating: PerformanceRating, average: float, ratings_count: int}> */
    private array $computedRatings = [];

    public function __construct(
        private readonly ?int $fieldOfficeId = null,
        private readonly ?int $year = null,
        private readonly ?int $examTypeId = null,
        private readonly ?int $memberId = null,
        private readonly ?PerformanceRatingCalculator $ratingCalculator = null,
    ) {}

    public function collection(): Collection
    {
        $assignments = ExamAssignment::query()
            ->with([
                'member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'examination:id,title,exam_type_id,exam_date',
                'fieldOffice:id,name,code',
                // For the Service Note column — without these the note would
                // lazy-load two relations per row across a whole region's records.
                'coveringFor.member:id,first_name,middle_name,last_name,suffix',
                'coveredBy.member:id,first_name,middle_name,last_name,suffix',
            ])
            ->when($this->fieldOfficeId, fn ($q) => $q->where('field_office_id', $this->fieldOfficeId))
            ->when($this->memberId, fn ($q) => $q->where('member_id', $this->memberId))
            ->when($this->year || $this->examTypeId, fn ($q) => $q->whereHas('examination', function ($qq) {
                $qq->when($this->year, fn ($q3) => $q3->whereYear('exam_date', $this->year))
                    ->when($this->examTypeId, fn ($q3) => $q3->where('exam_type_id', $this->examTypeId));
            }))
            ->get()
            ->sortByDesc(fn (ExamAssignment $a) => $a->examination?->exam_date)
            ->values();

        $this->computedRatings = ($this->ratingCalculator ?? app(PerformanceRatingCalculator::class))
            ->computeForMany($assignments);

        return $assignments;
    }

    public function headings(): array
    {
        // "Attendance" replaces the old Yes/No "Attendance Confirmed": a blank
        // no longer means only "unrecorded", so a boolean would now hide a
        // recorded absence behind the same "No" as a missing scan.
        return ['PROCTAD ID', 'Member', 'Field Office', 'Examination', 'Exam Date', 'Role', 'Attendance', 'Service Note', 'Rating'];
    }

    public function map($assignment): array
    {
        $rating = ($this->computedRatings[$assignment->id]['rating'] ?? null) ?? $assignment->performance_rating;

        return $this->safeRow([
            $assignment->member?->proctad_id,
            $assignment->member?->name,
            $assignment->fieldOffice?->name,
            $assignment->examination?->title,
            $assignment->examination?->exam_date?->format('Y-m-d'),
            $assignment->role->label(),
            $assignment->attendanceOutcome(),
            $assignment->serviceNote(),
            $rating?->label(),
        ]);
    }
}
