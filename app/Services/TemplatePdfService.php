<?php

namespace App\Services;

use App\Support\PdfXrefRepair;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Places a full-bleed template (PDF page or raster image) as the background
 * of the current page, so callers can then draw dynamic content on top with
 * plain FPDF methods — used for both PDF letterheads and (potentially) other
 * pre-printed document backgrounds.
 */
class TemplatePdfService
{
    public function importBackground(Fpdi $pdf, string $path, float $w, float $h): void
    {
        if (str_ends_with(strtolower($path), '.pdf')) {
            $this->importPdfBackground($pdf, $path, $w, $h);

            return;
        }

        $pdf->Image($path, 0, 0, $w, $h);
    }

    private function importPdfBackground(Fpdi $pdf, string $path, float $w, float $h): void
    {
        try {
            $pdf->setSourceFile($path);
        } catch (Throwable $e) {
            if (! PdfXrefRepair::isNeeded($e)) {
                throw $e;
            }

            $pdf->setSourceFile(PdfXrefRepair::repair($path));
        }

        $tpl = $pdf->importPage(1);
        $pdf->useTemplate($tpl, 0, 0, $w, $h);
    }
}
