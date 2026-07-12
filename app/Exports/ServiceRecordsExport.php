<?php

namespace App\Exports;

use App\Models\ExamAssignment;
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
    public function __construct(
        private readonly ?int $fieldOfficeId = null,
        private readonly ?int $year = null,
        private readonly ?int $examTypeId = null,
        private readonly ?int $memberId = null,
    ) {}

    public function collection(): Collection
    {
        return ExamAssignment::query()
            ->with(['member:id,proctad_id,first_name,middle_name,last_name,suffix', 'examination:id,title,exam_type_id,exam_date', 'fieldOffice:id,name,code'])
            ->when($this->fieldOfficeId, fn ($q) => $q->where('field_office_id', $this->fieldOfficeId))
            ->when($this->memberId, fn ($q) => $q->where('member_id', $this->memberId))
            ->when($this->year || $this->examTypeId, fn ($q) => $q->whereHas('examination', function ($qq) {
                $qq->when($this->year, fn ($q3) => $q3->whereYear('exam_date', $this->year))
                    ->when($this->examTypeId, fn ($q3) => $q3->where('exam_type_id', $this->examTypeId));
            }))
            ->get()
            ->sortByDesc(fn (ExamAssignment $a) => $a->examination?->exam_date)
            ->values();
    }

    public function headings(): array
    {
        return ['PROCTAD ID', 'Member', 'Testing Center', 'Examination', 'Exam Date', 'Role', 'Attendance Confirmed', 'Rating'];
    }

    public function map($assignment): array
    {
        return [
            $assignment->member?->proctad_id,
            $assignment->member?->name,
            $assignment->fieldOffice?->name,
            $assignment->examination?->title,
            $assignment->examination?->exam_date?->format('Y-m-d'),
            $assignment->role->label(),
            $assignment->attendance_confirmed_at ? 'Yes' : 'No',
            $assignment->performance_rating?->label(),
        ];
    }
}
