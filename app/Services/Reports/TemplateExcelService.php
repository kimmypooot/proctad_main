<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Thin, stateful wrapper around PhpSpreadsheet for generating reports from a
 * pre-built .xlsx template rather than from scratch. Centralizes the one
 * piece of logic that would otherwise be duplicated across report builders:
 * loading a template, writing rows while preserving the template's exact
 * styling (cloning it onto any rows written beyond the template's own
 * pre-formatted block), and streaming the result back as a download.
 *
 * This does not stream in the memory sense — PhpSpreadsheet must materialize
 * the whole workbook regardless of dataset size. Performance for large
 * rosters is a query-side concern (eager loading, no N+1), not something
 * this service can address.
 */
class TemplateExcelService
{
    private Spreadsheet $spreadsheet;

    private Worksheet $sheet;

    public function load(string $templatePath, ?string $sheetName = null): static
    {
        $this->spreadsheet = IOFactory::load($templatePath);
        $this->sheet = $sheetName !== null
            ? $this->spreadsheet->getSheetByName($sheetName)
            : $this->spreadsheet->getActiveSheet();

        return $this;
    }

    public function sheet(): Worksheet
    {
        return $this->sheet;
    }

    public function setCell(string $coordinate, mixed $value): static
    {
        $this->sheet->setCellValue($coordinate, $value);

        return $this;
    }

    /**
     * Writes $rows (each an array of column-letter => value) starting at
     * $startRow. Rows beyond $templateLastPreformattedRow have their style
     * and height cloned from that last template row before being written, so
     * rosters longer than the template's built-in placeholder block still
     * look identical to the hand-built rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string[]  $columns
     */
    public function writeRows(array $rows, int $startRow, int $templateLastPreformattedRow, array $columns): static
    {
        foreach (array_values($rows) as $i => $rowData) {
            $rowNum = $startRow + $i;

            if ($rowNum > $templateLastPreformattedRow) {
                $this->cloneRowStyle($templateLastPreformattedRow, $rowNum, $columns);
            }

            foreach ($columns as $col) {
                if (array_key_exists($col, $rowData)) {
                    $this->sheet->setCellValue("{$col}{$rowNum}", $rowData[$col]);
                }
            }
        }

        return $this;
    }

    /**
     * Blanks out cell values (styling untouched) across $columns for every
     * row in [$fromRow, $toRow]. The provided templates are actual filled
     * documents from a past exam rather than blank forms, so any
     * pre-formatted row a report doesn't write its own data into must be
     * explicitly cleared here — otherwise a prior exam's real personnel data
     * would silently carry over into the generated file.
     *
     * @param  string[]  $columns
     */
    public function clearRows(int $fromRow, int $toRow, array $columns): static
    {
        for ($row = $fromRow; $row <= $toRow; $row++) {
            foreach ($columns as $col) {
                $this->sheet->setCellValue("{$col}{$row}", null);
            }
        }

        return $this;
    }

    /**
     * Inserts $count blank rows before $beforeRow, shifting everything below
     * (including merged cells and formulas) downward. Use before writing
     * roster rows that would otherwise overwrite a fixed block (e.g. a
     * certification/signatory block) that follows a pre-formatted roster
     * section in the template.
     */
    public function insertRowsBefore(int $beforeRow, int $count): static
    {
        if ($count > 0) {
            $this->sheet->insertNewRowBefore($beforeRow, $count);
        }

        return $this;
    }

    private function cloneRowStyle(int $fromRow, int $toRow, array $columns): void
    {
        foreach ($columns as $col) {
            $this->sheet->duplicateStyle($this->sheet->getStyle("{$col}{$fromRow}"), "{$col}{$toRow}");
        }

        $this->sheet->getRowDimension($toRow)->setRowHeight(
            $this->sheet->getRowDimension($fromRow)->getRowHeight()
        );
    }

    public function download(string $filename): BinaryFileResponse
    {
        $writer = new Xlsx($this->spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'proctad_report_').'.xlsx';
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
