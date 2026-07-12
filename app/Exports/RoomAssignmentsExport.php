<?php

namespace App\Exports;

use App\Models\Examination;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Per-room Proctor / Room Examiner / Supervising Examiner breakdown for one
 * examination, optionally scoped to a single testing center and/or filtered
 * to complete/incomplete rooms — mirrors whatever the admin has filtered on
 * the Assign Rooms step. Formatted for printing (title block, bold header,
 * autofilter, incomplete-row highlight, landscape print setup).
 */
class RoomAssignmentsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    private const LAST_COLUMN = 'H';

    public function __construct(
        private readonly Collection $rows,
        private readonly Examination $examination,
        private readonly ?string $venueLabel = null,
        private readonly string $statusFilter = 'all',
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Testing Center', 'Room Number', 'Capacity', 'Designation', 'Proctor', 'Room Examiner', 'Supervising Examiner', 'Status'];
    }

    public function map($row): array
    {
        return [
            $row['venue_name'],
            $row['room_number'],
            $row['capacity'],
            $row['designation'] ?: '—',
            $row['proctor'] ?: 'Unassigned',
            $row['room_examiner'] ?: 'Unassigned',
            $row['supervising_examiner'] ?: 'Unassigned',
            $row['complete'] ? 'Complete' : 'Incomplete',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = self::LAST_COLUMN;
                $headerRow = 4;
                $firstDataRow = $headerRow + 1;
                $lastDataRow = max($firstDataRow, $headerRow + $this->rows->count());

                // WithHeadings put the header at row 1 with data following; push it
                // down 3 rows to make room for a title block above it.
                $sheet->insertNewRowBefore(1, 3);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->setCellValue('A1', sprintf(
                    'Room Assignments — %s (%s)',
                    $this->examination->title,
                    $this->examination->exam_date->format('F j, Y'),
                ));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastColumn}2");
                $subtitle = implode('  ·  ', array_filter([
                    $this->venueLabel ? "Testing Center: {$this->venueLabel}" : 'All Testing Centers',
                    $this->statusFilter !== 'all' ? 'Status: '.ucfirst($this->statusFilter) : null,
                    'Generated '.now()->format('M j, Y g:i A'),
                ]));
                $sheet->setCellValue('A2', $subtitle);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle('A2')->getFont()->getColor()->setRGB('666666');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('1E3A5F');
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                if ($this->rows->count() > 0) {
                    $dataRange = "A{$firstDataRow}:{$lastColumn}{$lastDataRow}";
                    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("B{$firstDataRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$firstDataRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        if ($sheet->getCell("H{$r}")->getValue() === 'Incomplete') {
                            $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FEF3C7');
                        }
                    }

                    $sheet->setAutoFilter($headerRange);
                }

                $sheet->freezePane("A{$firstDataRow}");
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}
