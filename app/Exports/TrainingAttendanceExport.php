<?php

namespace App\Exports;

use App\Exports\Concerns\EscapesFormulas;
use App\Models\TrainingAssignment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TrainingAttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    use EscapesFormulas;

    public function __construct(
        private readonly ?int $fieldOfficeId = null,
        private readonly ?int $year = null,
        private readonly ?int $trainingId = null,
    ) {}

    public function collection(): Collection
    {
        return TrainingAssignment::query()
            ->with(['member:id,proctad_id,first_name,middle_name,last_name,suffix', 'training:id,title,type,training_date', 'fieldOffice:id,name,code'])
            ->when($this->fieldOfficeId, fn ($q) => $q->where('field_office_id', $this->fieldOfficeId))
            ->when($this->trainingId, fn ($q) => $q->where('training_id', $this->trainingId))
            ->when($this->year, fn ($q) => $q->whereHas('training', fn ($qq) => $qq->whereYear('training_date', $this->year)))
            ->get()
            ->sortByDesc(fn (TrainingAssignment $a) => $a->training?->training_date)
            ->values();
    }

    public function headings(): array
    {
        return ['PROCTAD ID', 'Member', 'Field Office', 'Training', 'Training Date', 'Attendance Confirmed'];
    }

    public function map($assignment): array
    {
        return $this->safeRow([
            $assignment->member?->proctad_id,
            $assignment->member?->name,
            $assignment->fieldOffice?->name,
            $assignment->training?->title,
            $assignment->training?->training_date?->format('Y-m-d'),
            $assignment->attendance_confirmed_at ? 'Yes' : 'No',
        ]);
    }
}
