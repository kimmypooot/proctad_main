<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MembersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly ?int $fieldOfficeId = null, private readonly ?string $status = null) {}

    public function collection(): Collection
    {
        return Member::query()
            ->with('fieldOffice:id,name,code')
            ->when($this->fieldOfficeId, fn ($q) => $q->where('field_office_id', $this->fieldOfficeId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('last_name')
            ->get();
    }

    public function headings(): array
    {
        return ['PROCTAD ID', 'Name', 'Sex', 'Email', 'Mobile Number', 'Agency', 'Position', 'Field Office', 'Status', 'Registered'];
    }

    public function map($member): array
    {
        return [
            $member->proctad_id,
            $member->name,
            ucfirst($member->sex),
            $member->email,
            $member->mobile_number,
            $member->agency,
            $member->position,
            $member->fieldOffice?->name,
            $member->status->label(),
            $member->created_at->format('Y-m-d'),
        ];
    }
}
