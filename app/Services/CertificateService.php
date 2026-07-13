<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Mail\CertificateReleased;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Letterhead;
use App\Models\Signatory;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Notifications\CertificateDecided;
use App\Notifications\CertificatePendingApproval;
use App\Notifications\MemberCertificateDecided;
use App\Support\BrandedQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

class CertificateService
{
    /**
     * Queue a certificate for approval. Idempotent per (type, source record):
     * re-scans never create duplicates. Types with no approver (currently
     * only Completion — spec 3.2) are released immediately instead of ever
     * touching Pending: nobody holds `decide()` rights for a null approver
     * role, so leaving one pending would strand it forever.
     */
    public function generatePending(
        CertificateType $type,
        ExamAssignment|TrainingAssignment $source,
        User $requestedBy,
    ): Certificate {
        $certificate = Certificate::firstOrCreate([
            'type' => $type,
            'certifiable_type' => $source::class,
            'certifiable_id' => $source->id,
        ], [
            'member_id' => $source->member_id,
            'field_office_id' => $source->field_office_id,
            'status' => CertificateStatus::Pending,
            'requested_by' => $requestedBy->id,
        ]);

        if (! $certificate->wasRecentlyCreated) {
            return $certificate;
        }

        if ($type->approverRole() === null) {
            return $this->release($certificate, $requestedBy);
        }

        $this->notifyApprovers($certificate);

        return $certificate;
    }

    /**
     * Approve + electronically release: snapshot the current signatory (later
     * signatory changes never touch issued certificates — spec 2.3), mint the
     * certificate number, render the PDF, email it, and mark it released so it
     * appears in the member's account.
     */
    public function release(Certificate $certificate, User $approver): Certificate
    {
        $signatory = Signatory::currentFor($certificate->field_office_id);

        $certificate->update([
            'status' => CertificateStatus::Released,
            'certificate_no' => $this->mintNumber($certificate),
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'signatory_name' => $signatory?->name,
            'signatory_position' => $signatory?->position,
            'released_at' => now(),
        ]);

        $certificate->update(['pdf_path' => $this->renderPdf($certificate)]);

        $member = $certificate->member;
        if ($member?->email) {
            Mail::to($member->email)->send(new CertificateReleased($certificate));
        }

        $this->notifyRequester($certificate);
        $this->notifyMember($certificate);

        return $certificate;
    }

    /**
     * Bulk re-sign: swap the signatory on an already-released certificate and
     * regenerate its PDF, without touching certificate_no/status/released_at.
     * Ported from bulk-resign-certificates.php. Unlike the legacy version,
     * every certificate type is supported — the unified Blade template has no
     * per-type PDF-overlay geometry to account for.
     */
    public function resign(Certificate $certificate, Signatory $signatory): Certificate
    {
        $certificate->update([
            'signatory_name' => $signatory->name,
            'signatory_position' => $signatory->position,
        ]);

        $certificate->update(['pdf_path' => $this->renderPdf($certificate)]);

        return $certificate;
    }

    /**
     * Re-render and overwrite a released certificate's stored PDF without
     * changing its certificate_no/status/signatory — used when the active
     * letterhead or template geometry changes and already-issued certificates
     * need to reflect it (see `certificates:regenerate-pdfs`).
     */
    public function regeneratePdf(Certificate $certificate): Certificate
    {
        $certificate->update(['pdf_path' => $this->renderPdf($certificate)]);

        return $certificate;
    }

    public function disapprove(Certificate $certificate, User $approver, string $remarks): Certificate
    {
        $certificate->update([
            'status' => CertificateStatus::Disapproved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'disapproval_remarks' => $remarks,
        ]);

        $this->notifyRequester($certificate);
        $this->notifyMember($certificate);

        return $certificate;
    }

    /**
     * Bell-notify the certificate type's approver role (FO-scoped for Field
     * Director), plus Super Admin as the region-wide fallback approver.
     */
    private function notifyApprovers(Certificate $certificate): void
    {
        $approverRole = $certificate->type->approverRole();

        if ($approverRole === null) {
            return;
        }

        $recipients = User::query()
            ->where(function ($query) use ($approverRole, $certificate) {
                $query->where('role', $approverRole)
                    ->when($approverRole === UserRole::FieldDirector,
                        fn ($q) => $q->where('field_office_id', $certificate->field_office_id));
            })
            ->orWhere('role', UserRole::SuperAdmin)
            ->get();

        Notification::send($recipients, new CertificatePendingApproval($certificate));
    }

    /**
     * Bell-notify the staff member who originally requested the certificate
     * once an approver has released or disapproved it.
     */
    private function notifyRequester(Certificate $certificate): void
    {
        if ($certificate->requestedBy) {
            $certificate->requestedBy->notify(new CertificateDecided($certificate));
        }
    }

    /**
     * Bell-notify the certificate's OWNING member (not the staff requester
     * above) via their linked self-service account, if they have one — the
     * in-app half of "approved/disapproved," alongside the release email.
     */
    private function notifyMember(Certificate $certificate): void
    {
        $certificate->loadMissing('member.user');

        if ($certificate->member?->user) {
            $certificate->member->user->notify(new MemberCertificateDecided($certificate));
        }
    }

    private function mintNumber(Certificate $certificate): string
    {
        return sprintf(
            'RO8-%s-%s-%05d',
            $certificate->type->code(),
            now()->year,
            $certificate->id,
        );
    }

    private function renderPdf(Certificate $certificate): string
    {
        $certificate->loadMissing('member.fieldOffice', 'certifiable');

        $letterhead = Letterhead::active();
        $path = "certificates/{$certificate->certificate_no}.pdf";

        // A PDF letterhead (e.g. the official CSC RO VIII template) can't be
        // composited as an HTML <img> background, so it gets its own FPDI
        // renderer that imports the real PDF page and draws content on top.
        // Image letterheads (or none) keep the existing dompdf/HTML path.
        if ($letterhead?->isPdf() && Storage::disk('local')->exists($letterhead->file_path)) {
            $bytes = $this->renderPdfWithPdfLetterhead($certificate, $letterhead);
        } else {
            $bytes = Pdf::loadView('certificates.certificate', [
                'certificate' => $certificate,
                'qrDataUri' => $this->qrDataUri(route('verify-certificate', $certificate->certificate_no)),
                'verifyUrl' => route('verify-certificate', $certificate->certificate_no),
                'letterheadDataUri' => $this->letterheadDataUri(),
            ])->setPaper('a4', 'landscape')->output();
        }

        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /**
     * Mirrors the legacy streaming PDF builders (api/appreciation-pdf-builder.php,
     * appearance-pdf-builder.php, completion-pdf-builder.php) content, fonts
     * (Times), and Y-coordinates almost exactly, drawn with FPDF onto the true
     * imported letterhead PDF page. One deliberate departure from legacy: the
     * bottom QR is always the branded certificate-verification QR (linking to
     * `verify-certificate`) rather than legacy's static feedback-survey image,
     * and it appears for every type instead of only exam-linked ones — a
     * functional upgrade the legacy system never had, kept in the same visual
     * slot/size legacy used for its QR.
     */
    private function renderPdfWithPdfLetterhead(Certificate $certificate, Letterhead $letterhead): string
    {
        $member = $certificate->member;
        $source = $certificate->certifiable;
        $verifyUrl = route('verify-certificate', $certificate->certificate_no);

        $PW = 210.0;
        $PH = 297.0;
        $ML = 25.4;
        $MR = 25.4;
        $cw = $PW - $ML - $MR;
        $black = [0, 0, 0];

        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetMargins($ML, 10, $MR);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        (new TemplatePdfService)->importBackground($pdf, Storage::disk('local')->path($letterhead->file_path), $PW, $PH);

        $setRGB = fn (array $c) => $pdf->SetTextColor($c[0], $c[1], $c[2]);
        $putCtr = function (float $y, string $style, float $sz, array $rgb, string $text) use ($pdf, $ML, $cw, $setRGB) {
            $setRGB($rgb);
            $pdf->SetFont('Times', $style, $sz);
            $pdf->SetXY($ML, $y);
            $pdf->Cell($cw, 0, $this->latin1($text), 0, 0, 'C');
        };
        $multiCtr = function (float $y, string $style, float $sz, array $rgb, float $lh, string $text) use ($pdf, $ML, $cw, $setRGB) {
            $setRGB($rgb);
            $pdf->SetFont('Times', $style, $sz);
            $pdf->SetXY($ML, $y);
            $pdf->MultiCell($cw, $lh, $this->latin1($text), 0, 'C');

            return $pdf->GetY();
        };

        match ($certificate->type->value) {
            'appreciation' => $this->drawAppreciation($pdf, $putCtr, $multiCtr, $member, $certificate, $source, $black),
            'appearance' => $this->drawAppearance($pdf, $putCtr, $multiCtr, $member, $certificate, $source, $ML, $cw, $black),
            'completion' => $this->drawCompletion($pdf, $putCtr, $member, $certificate, $source, $black),
            'designation_order' => $this->drawDesignationOrder($pdf, $putCtr, $multiCtr, $member, $certificate, $source, $black),
        };

        // No signature-image asset exists yet on the Signatory model (name/position
        // only), so unlike legacy this block is text-only — nothing to draw above it.
        if ($certificate->signatory_name) {
            $putCtr($this->signatureY, 'B', $this->signatureFontSize, $black, mb_strtoupper($certificate->signatory_name));
            $putCtr($this->signatureY + 5.7, '', $this->signatureFontSize - 1, $black, (string) $certificate->signatory_position);
        }

        // Verification QR in the same slot/size legacy used for its feedback QR.
        $qrFile = tempnam(sys_get_temp_dir(), 'proctad_cert_qr_').'.png';
        file_put_contents($qrFile, BrandedQrCode::png($verifyUrl));
        $maxW = 34.0;
        $maxH = 34.0;
        $qrX = $ML + ($cw - $maxW) / 2;
        $pdf->Image($qrFile, $qrX, 205, $maxW, $maxH);
        @unlink($qrFile);

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('Times', 'I', 8.5);
        $pdf->SetXY($ML, 242);
        $pdf->MultiCell($cw, 4, $this->latin1(
            "Scan to verify this certificate's authenticity.\n{$certificate->certificate_no}"
        ), 0, 'C');

        return $pdf->Output('S');
    }

    private float $signatureY = 0.0;

    private int $signatureFontSize = 11;

    private function drawAppreciation($pdf, callable $putCtr, callable $multiCtr, $member, Certificate $certificate, $source, array $black): void
    {
        $name = $this->nameFirstMiLast($member, comma: false);
        $rolePerformed = $source?->role?->label() ?? 'Test Administrator';

        $y = 60.0;
        $putCtr($y, '', 12, $black, 'This');
        $putCtr($y += 6, 'B', 22, $black, 'CERTIFICATE OF APPRECIATION');
        $putCtr($y += 9, '', 12, $black, 'is presented to');
        $putCtr($y += 15, 'BU', 20, $black, $name);
        $putCtr($y += 12, '', 12, $black, 'in grateful recognition of your invaluable service as');
        $putCtr($y += 13, 'B', 18, $black, $rolePerformed);

        $examParagraph = 'during the conduct of the '.$certificate->sourceDescription()
            .' examination on '.$certificate->sourceDate().'.';

        $pdf->SetFont('Times', '', 12);
        $pdf->SetXY(25.4, 123);
        $pdf->MultiCell(159.2, 5, $this->latin1($examParagraph), 0, 'C');
        $afterExam = $pdf->GetY();

        $yContrib = max($afterExam, 130);
        $pdf->SetXY(25.4, $yContrib);
        $pdf->MultiCell(159.2, 5, $this->latin1('Your service contributed significantly to the successful conduct of the examination.'), 0, 'C');
        $afterContrib = $pdf->GetY();

        $yGiven = max($afterContrib, 150);
        $issue = $certificate->released_at ?? now();
        $putCtr($yGiven, '', 12, $black, 'Given this '.$this->ordinal((int) $issue->format('j')).' day of '.$issue->format('F Y').', Palo, Leyte');

        $this->signatureY = $yGiven + 40;
        $this->signatureFontSize = 11;
    }

    private function drawAppearance($pdf, callable $putCtr, callable $multiCtr, $member, Certificate $certificate, $source, float $ML, float $cw, array $black): void
    {
        $name = $this->nameLastFirst($member);

        $putCtr(65.6, 'B', 18, $black, 'CERTIFICATE OF APPEARANCE');

        $segCertify = 'This is to certify that ';
        $segThanks = ' attended and appeared in the ';
        $segRest = $certificate->sourceDescription().' on '.$certificate->sourceDate().'.';

        $yStart = 86.0;
        $lh = 7.5;

        $pdf->SetFont('Times', '', 15);
        $wCertify = $pdf->GetStringWidth($this->latin1($segCertify));
        $wThanks = $pdf->GetStringWidth($this->latin1($segThanks));
        $wRest = $pdf->GetStringWidth($this->latin1($segRest));
        $pdf->SetFont('Times', 'BU', 20);
        $wName = $pdf->GetStringWidth($this->latin1($name));

        $wPacked = $wCertify + $wName + $wThanks;

        if ($wPacked <= $cw) {
            $x = $ML + ($cw - $wPacked) / 2;
            $pdf->SetXY($x, $yStart);
            $pdf->SetTextColor(...$black);
            $pdf->SetFont('Times', '', 15);
            $pdf->Cell($wCertify, $lh, $this->latin1($segCertify), 0, 0, 'L');
            $pdf->SetFont('Times', 'BU', 20);
            $pdf->Cell($wName, $lh, $this->latin1($name), 0, 0, 'L');
            $pdf->SetFont('Times', '', 15);
            $pdf->Cell($wThanks, $lh, $this->latin1($segThanks), 0, 0, 'L');

            if ($wPacked + $wRest <= $cw) {
                $pdf->Cell($wRest, $lh, $this->latin1($segRest), 0, 0, 'L');
                $afterBody = $yStart + $lh;
            } else {
                $afterBody = $multiCtr($yStart + $lh + 1.0, '', 15, $black, $lh, $segRest);
            }
        } else {
            $x = $ML + max(0, ($cw - $wCertify - $wName) / 2);
            $pdf->SetXY($x, $yStart);
            $pdf->SetTextColor(...$black);
            $pdf->SetFont('Times', '', 15);
            $pdf->Cell($wCertify, $lh, $this->latin1($segCertify), 0, 0, 'L');
            $pdf->SetFont('Times', 'BU', 20);
            $pdf->Cell($wName, $lh, $this->latin1($name), 0, 0, 'L');
            $afterBody = $multiCtr($yStart + $lh + 4.0, '', 15, $black, $lh, $segThanks.$segRest);
        }

        $yIssued = max($afterBody + 14, 134.3);
        $issue = $certificate->released_at ?? now();
        $location = $member->fieldOffice?->name ?? 'Palo, Leyte';
        $issuedLine = 'Issued this '.$this->ordinal((int) $issue->format('j')).' day of '.$issue->format('F Y').' at '.$location.' for whatever lawful purpose this may serve.';

        $pdf->SetFont('Times', '', 15);
        if ($pdf->GetStringWidth($this->latin1($issuedLine)) <= $cw) {
            $putCtr($yIssued, '', 15, $black, $issuedLine);
            $afterIssued = $yIssued + 7.5;
        } else {
            $afterIssued = $multiCtr($yIssued, '', 15, $black, 7.5, $issuedLine);
        }

        $this->signatureY = $afterIssued + 35;
        $this->signatureFontSize = 14;
    }

    private function drawCompletion($pdf, callable $putCtr, $member, Certificate $certificate, $source, array $black): void
    {
        $name = $this->nameFirstMiLast($member, comma: true);

        $y = 65.0;
        $lh = 16.0;
        $putCtr($y, '', 14, $black, 'This');
        $y += $lh;
        $putCtr($y, 'B', 33, $black, 'Certificate of Completion');
        $y += $lh;
        $putCtr($y, '', 14, $black, 'is awarded to');
        $y += $lh - 5;
        $putCtr($y, 'B', 27, $black, $name);
        $y += $lh;
        $putCtr($y, '', 14, $black, 'for having successfully completed');
        $y += $lh - 5;
        $putCtr($y, 'B', 27, $black, $certificate->sourceDescription());
        $y += $lh;
        $putCtr($y, '', 14, $black, 'conducted onsite by the');
        $y += $lh - 8;
        $putCtr($y, '', 14, $black, 'Civil Service Commission Regional Office VIII on');
        $y += $lh - 8;
        $putCtr($y, '', 14, $black, (string) $certificate->sourceDate());
        $y += $lh - 8;

        $this->signatureY = max($y + 12, 220.0);
        $this->signatureFontSize = 14;
    }

    private function drawDesignationOrder($pdf, callable $putCtr, callable $multiCtr, $member, Certificate $certificate, $source, array $black): void
    {
        $name = $this->nameFirstMiLast($member, comma: false);

        $y = 65.0;
        $putCtr($y, '', 14, $black, 'This');
        $putCtr($y += 16, 'B', 28, $black, 'DESIGNATION ORDER');
        $putCtr($y += 16, '', 14, $black, 'Be it known that');
        $putCtr($y += 16, 'B', 24, $black, $name);

        $body = 'is hereby designated to serve in the '.$certificate->sourceDescription()
            .' on '.$certificate->sourceDate().', subject to existing Civil Service Commission rules and regulations.';

        $afterBody = $multiCtr($y + 16, '', 13, $black, 6, $body);

        $this->signatureY = $afterBody + 30;
        $this->signatureFontSize = 12;
    }

    private function nameLastFirst($member): string
    {
        $mi = $member->middle_name ? ' '.mb_strtoupper(mb_substr($member->middle_name, 0, 1)).'.' : '';
        $sfx = $member->suffix ? ' '.mb_strtoupper($member->suffix) : '';

        return mb_strtoupper($member->last_name).', '.mb_strtoupper($member->first_name).$mi.$sfx;
    }

    private function nameFirstMiLast($member, bool $comma): string
    {
        $mi = $member->middle_name ? ' '.mb_strtoupper(mb_substr($member->middle_name, 0, 1)).'.' : '';
        $sfx = $member->suffix ? ($comma ? ', ' : ' ').mb_strtoupper($member->suffix) : '';

        return mb_strtoupper($member->first_name).$mi.' '.mb_strtoupper($member->last_name).$sfx;
    }

    /** FPDF core fonts are Latin-1 — convert all DB-sourced display strings. */
    private function latin1(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    private function ordinal(int $n): string
    {
        $s = 'th';
        if (! in_array($n % 100, [11, 12, 13])) {
            $s = match ($n % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };
        }

        return $n.$s;
    }

    /**
     * The active letterhead image as a data URI so dompdf can composite it as
     * the certificate background (spec 2.3: letterhead is region-wide and
     * updatable without touching previously issued certificates — this reads
     * the currently active one at render time only). Falls back to null (the
     * template then draws its own self-contained frame) when no letterhead is
     * active or the file is missing.
     */
    private function letterheadDataUri(): ?string
    {
        $letterhead = Letterhead::active();

        if ($letterhead === null || ! Storage::disk('local')->exists($letterhead->file_path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($letterhead->file_path);
        $mime = Storage::disk('local')->mimeType($letterhead->file_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Branded QR (ProCTAD logo centered, ECC H) as a PNG data URI for dompdf;
     * falls back to null (the template then prints the verification URL as
     * text) if GD is unavailable.
     */
    private function qrDataUri(string $url): ?string
    {
        try {
            return BrandedQrCode::dataUri($url);
        } catch (Throwable) {
            return null;
        }
    }
}
