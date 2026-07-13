<?php

namespace App\Services;

use App\Models\Member;
use App\Models\OtherExaminationPersonnel;
use App\Support\BrandedQrCode;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Printable PROCTAD/OEP ID cards on the official F-ID-Template.jpg /
 * B-ID-Template.jpg art — geometry, fonts, and print layout mirror legacy's
 * api/user/download-id.php (single) and api/superadmin/download-nep-id.php
 * (bulk, which fixed a back-page slot bug present in the older
 * testing_center/download-id.php — both bulk paths now use that logic).
 */
class IdCardPdfService
{
    private const CARD_W = 80.0;

    private const CARD_H = 110.0;

    private const PAGE_W = 210.0;

    private const PAGE_H = 297.0;

    private const MARGIN = 10.0;

    // top-left, top-right, bottom-left, bottom-right
    private const BACK_MIRROR = [1, 0, 3, 2];

    public function renderMember(Member $member): string
    {
        return $this->renderSingle($this->memberData($member));
    }

    public function renderMembersBulk(iterable $members): string
    {
        return $this->renderBulk(collect($members)->map(fn (Member $m) => $this->memberData($m)));
    }

    public function renderOep(OtherExaminationPersonnel $oep): string
    {
        return $this->renderSingle($this->oepData($oep));
    }

    public function renderOepsBulk(iterable $oeps): string
    {
        return $this->renderBulk(collect($oeps)->map(fn (OtherExaminationPersonnel $o) => $this->oepData($o)));
    }

    /**
     * Front and back side by side on one A4 page, centered — matches
     * legacy's single-user download-id.php exactly.
     */
    private function renderSingle(array $card): string
    {
        $pdf = $this->newPdf();

        $gap = 5.0;
        $totalWidth = self::CARD_W * 2 + $gap;
        $startX = (self::PAGE_W - $totalWidth) / 2;
        $y = (self::PAGE_H - self::CARD_H) / 2;

        $pdf->AddPage();
        $this->drawFront($pdf, $card, $startX, $y);
        $this->drawBack($pdf, $startX + self::CARD_W + $gap, $y);

        return $pdf->Output('S');
    }

    /**
     * 2x2 grid per page for fronts, then one aggregated back page whose
     * filled slots are mirrored left/right to align after a duplex flip —
     * matches legacy's corrected (OEP) bulk logic.
     */
    private function renderBulk($cards): string
    {
        $cards = $cards->values();
        $pdf = $this->newPdf();
        $positions = $this->gridPositions();
        $total = $cards->count();
        $backCount = (int) ceil($total / 4);

        $posIdx = 4; // force page creation on the first card
        foreach ($cards as $card) {
            if ($posIdx >= 4) {
                $pdf->AddPage();
                $this->addInstructions($pdf, $backCount);
                $posIdx = 0;
            }
            [$x, $y] = $positions[$posIdx];
            $this->drawFront($pdf, $card, $x, $y);
            $posIdx++;
        }

        $slotsUsedOnLastPage = ($total % 4 === 0) ? 4 : $total % 4;
        $backSlots = array_slice(self::BACK_MIRROR, 0, $slotsUsedOnLastPage);

        $pdf->AddPage();
        $this->addInstructions($pdf, $backCount);
        foreach ($backSlots as $idx) {
            [$x, $y] = $positions[$idx];
            $this->drawBack($pdf, $x, $y);
        }

        return $pdf->Output('S');
    }

    private function newPdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        return $pdf;
    }

    private function gridPositions(): array
    {
        return [
            [self::MARGIN, self::MARGIN],
            [self::PAGE_W - self::CARD_W - self::MARGIN, self::MARGIN],
            [self::MARGIN, self::PAGE_H - self::CARD_H - self::MARGIN],
            [self::PAGE_W - self::CARD_W - self::MARGIN, self::PAGE_H - self::CARD_H - self::MARGIN],
        ];
    }

    private function addInstructions(Fpdi $pdf, int $backCount): void
    {
        $cx = self::PAGE_W / 2;
        $cy = self::PAGE_H / 2;

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(100, 100, 100);

        $lines = [
            'STEP 1: Print all pages except the last page',
            'STEP 2: Flip the papers over in the printer',
            'STEP 3: Print the last page '.($backCount > 1 ? $backCount.' times' : 'one time'),
        ];

        foreach ($lines as $i => $line) {
            $pdf->SetXY($cx - 50, $cy - 8 + $i * 7);
            $pdf->Cell(100, 5, $line, 0, 0, 'C');
        }
    }

    private function drawFront(Fpdi $pdf, array $card, float $x, float $y): void
    {
        $pdf->Image(public_path('images/id-templates/F-ID-Template.jpg'), $x, $y, self::CARD_W, self::CARD_H);

        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY($x, $y + 27);
        $pdf->Cell(self::CARD_W, 6, $card['role'], 0, 0, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY($x, $y + 33);
        $pdf->Cell(self::CARD_W, 4, $card['id_label'], 0, 0, 'C');

        $photoSize = 30.0;
        $photoX = $x + 3;
        $photoY = $y + 42;

        if ($card['photo_path']) {
            $pdf->Image($card['photo_path'], $photoX, $photoY, $photoSize, $photoSize);
        } else {
            $pdf->SetFillColor(0, 119, 182);
            $pdf->Rect($photoX, $photoY, $photoSize, $photoSize, 'F');
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($photoX, $photoY + $photoSize / 2 - 4);
            $pdf->Cell($photoSize, 8, $this->latin1($card['initials']), 0, 0, 'C');
        }

        $qrSize = 40.0;
        $qrX = $x + self::CARD_W - $qrSize - 3;
        $qrY = $y + 38;
        $qrFile = $this->writeTempPng(BrandedQrCode::png($card['qr_value'], $card['qr_logo']));
        $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);
        @unlink($qrFile);

        $nameFontSize = 12;
        $nameMaxWidth = self::CARD_W - 10;
        $nameLineH = 5.5;
        $pdf->SetFont('Helvetica', 'BU', $nameFontSize);

        while ($pdf->GetStringWidth($card['name']) > $nameMaxWidth && $nameFontSize > 7) {
            $nameFontSize -= 0.5;
            $nameLineH = $nameFontSize * 0.46;
            $pdf->SetFont('Helvetica', 'BU', $nameFontSize);
        }

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY($x + 5, $y + self::CARD_H - 32);
        $pdf->MultiCell($nameMaxWidth, $nameLineH, $card['name'], 0, 'C');
    }

    private function drawBack(Fpdi $pdf, float $x, float $y): void
    {
        $pdf->Image(public_path('images/id-templates/B-ID-Template.jpg'), $x, $y, self::CARD_W, self::CARD_H);
    }

    private function writeTempPng(string $bytes): string
    {
        $file = tempnam(sys_get_temp_dir(), 'proctad_qr_').'.png';
        file_put_contents($file, $bytes);

        return $file;
    }

    /** FPDF core fonts are Latin-1 — convert all DB-sourced display strings. */
    private function latin1(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    private function memberData(Member $member): array
    {
        $member->loadMissing('fieldOffice:id,name,code');
        $name = $this->latin1(mb_strtoupper($member->nameFirstLast()));

        return [
            'role' => 'PROCTAD',
            'id_label' => $member->proctad_id,
            'name' => $name,
            'initials' => mb_strtoupper(mb_substr($member->first_name, 0, 1).mb_substr($member->last_name, 0, 1)),
            'photo_path' => $member->photo_path && Storage::disk('local')->exists($member->photo_path)
                ? Storage::disk('local')->path($member->photo_path)
                : null,
            'qr_value' => route('verify', $member->proctad_id),
            'qr_logo' => true,
        ];
    }

    private function oepData(OtherExaminationPersonnel $oep): array
    {
        $oep->loadMissing('fieldOffice:id,name,code');
        $name = $this->latin1(mb_strtoupper($oep->fullName()));

        return [
            'role' => mb_strtoupper($oep->personnel_type->label()),
            'id_label' => $oep->oep_id,
            'name' => $name,
            'initials' => mb_strtoupper(mb_substr($oep->first_name, 0, 1).mb_substr($oep->last_name, 0, 1)),
            'photo_path' => $oep->photo_path && Storage::disk('local')->exists($oep->photo_path)
                ? Storage::disk('local')->path($oep->photo_path)
                : null,
            'qr_value' => "OEP:{$oep->oep_id}",
            // Other Examination Personnel are not part of the PROCTAD corps —
            // their QR shouldn't carry the ProCTAD program logo.
            'qr_logo' => false,
        ];
    }
}
