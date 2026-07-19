<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $certificate->certificate_no }}</title>
<style>
    /*
     * A4 portrait (595.28pt x 841.89pt) — matches both the official CSC
     * letterhead's own page geometry and the FPDI renderer in
     * CertificateService, which draws on a 210x297mm page.
     *
     * Everything is sized in pt and .sheet is absolutely positioned: an
     * absolutely positioned box never joins the normal flow, so dompdf
     * cannot paginate it. That is what keeps a certificate to exactly one
     * page. All of .sheet's children are absolute too, so both the
     * letterhead and no-letterhead variants share one page coordinate
     * system instead of each needing their own box maths.
     */
    @page { margin: 0; }
    html, body { margin: 0; padding: 0; }

    /* Local files only — dompdf's `enable_remote` is false, and these paths
       must stay within its chroot (base_path). Roboto/Mirante are declared
       bold-only: those are the only weights the design uses, and the bundled
       Roboto.ttf is a VARIABLE font that dompdf cannot interpolate (it would
       render the Regular instance), so the static Roboto-Bold.ttf is used. */
    @font-face { font-family: 'Roboto'; font-weight: bold; font-style: normal; src: url('{{ $fontDir }}/Roboto-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Mirante'; font-weight: bold; font-style: normal; src: url('{{ $fontDir }}/Mirante-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Calibri'; font-weight: normal; font-style: normal; src: url('{{ $fontDir }}/Calibri.ttf') format('truetype'); }
    @font-face { font-family: 'Calibri'; font-weight: bold; font-style: normal; src: url('{{ $fontDir }}/Calibri-Bold.ttf') format('truetype'); }

    body { font-family: 'Calibri', sans-serif; color: #000; }

    .sheet { position: absolute; top: 0; left: 0; width: 595pt; height: 841pt; }

    .letterhead-bg { position: absolute; top: 0; left: 0; width: 595pt; height: 841pt; z-index: 0; }

    /* No-letterhead fallback: a self-contained double frame stands in for the
       letterhead artwork. Purely decorative, hence its own boxes rather than
       borders on the content (which would disturb the shared coordinates). */
    .deco-outer { position: absolute; top: 21pt; left: 21pt; width: 549pt; height: 795pt; border: 2pt solid #2A338F; z-index: 0; }
    .deco-inner { position: absolute; top: 28pt; left: 28pt; width: 537pt; height: 783pt; border: 1pt solid #2A338F; z-index: 0; }

    /* 1in (72pt) left/right margins => 595.28 - 144 = 451pt of text column.
       Top starts below the letterhead's CSC seal; the Lingkod Bayani mark and
       footer ribbon begin around 690pt, so all content stays above that. */
    .content { position: absolute; left: 72pt; top: 148pt; width: 451pt; text-align: center; z-index: 1; }
    .content.plain { top: 92pt; }

    .org { font-size: 12pt; letter-spacing: 1.5pt; text-transform: uppercase; margin: 0; }
    .office { font-size: 12pt; font-weight: bold; margin: 3pt 0 0; }
    .program { font-size: 12pt; letter-spacing: 1pt; text-transform: uppercase; margin: 3pt 0 0; }

    /* Small "This" lead-in sitting just above the title, matching the legacy
       certificate builders (appreciation/completion/designation all open with
       it). Kept close to the title with a small bottom margin only. */
    .pretitle { font-size: 13pt; margin: 0 0 2pt; }

    /* Certificate title — Roboto bold, CSC red, all caps. */
    .title {
        font-family: 'Roboto', sans-serif;
        font-weight: bold;
        font-size: 26pt;
        color: #EC1C2D;
        margin: 0 0 4pt;
        text-transform: uppercase;
    }
    /* Participant name, source/training title, signatory name — Mirante bold,
       CSC blue. Both hexes are the app's brand palette and were verified
       against the CSC seal's own pixels in the letterhead artwork. */
    .name {
        font-family: 'Mirante', serif;
        font-weight: bold;
        font-size: 23pt;
        color: #2A338F;
        margin: 6pt 0 0;
    }
    /* Underline beneath the name, matching the Completion layout in the
       reference CertificateEngine (renderA4 draws a navy rule under it). */
    .name-rule { width: 300pt; margin: 3pt auto 0; border-top: 0.75pt solid #2A338F; }
    .source-title {
        font-family: 'Mirante', serif;
        font-weight: bold;
        font-size: 15pt;
        color: #2A338F;
        margin: 8pt 0 0;
        line-height: 1.35;
    }
    /* Everything else — Calibri, black, minimum 12pt. */
    .lead { font-size: 12pt; margin: 12pt 0 0; }
    .proctad-id { font-size: 12pt; margin: 4pt 0 0; }
    .trail { font-size: 12pt; line-height: 1.6; margin: 8pt 0 0; }
    .issued { font-size: 12pt; margin: 10pt 0 0; }

    /* Signatory sits in the content flow (not pinned at a fixed y), so it
       trails the body by a constant gap and floats up on short certificates
       instead of stranding a large empty space above it — mirroring
       renderA4's `contentEnd + 25mm` anchoring in the reference. The 54pt
       top margin is the space left for a wet signature to overlay the name. */
    .signatory { margin-top: 54pt; text-align: center; }
    /* With an e-signature the image itself fills the gap that was reserved for
       a wet signature, so the block sits closer to the body text. */
    .signatory-signed { margin-top: 20pt; }
    .sig-image { display: block; height: 40pt; width: auto; margin: 0 auto 2pt; }
    .sig-name {
        font-family: 'Mirante', serif;
        font-weight: bold;
        font-size: 13pt;
        color: #2A338F;
        margin: 0;
    }
    .sig-pos { font-size: 12pt; margin: 0; }

    /* Verification QR with the certificate number centred directly beneath
       it, in the band between the signatory and the letterhead's Lingkod
       Bayani footer mark (which starts ~712pt on the active letterhead). */
    .cert-footer { position: absolute; left: 0; top: 600pt; width: 595pt; text-align: center; z-index: 1; }
    .cert-footer .qr { display: block; width: 80pt; height: 80pt; margin: 0 auto 0; }
    .cert-footer .no { margin: -1pt 0 0; padding: 0; font-size: 10pt; font-weight: bold; line-height: 1; color: #3d3d3d; }

    .preview-banner {
        position: absolute;
        top: 0;
        left: 0;
        width: 595pt;
        background: #c80000;
        color: #fff;
        font-size: 10pt;
        font-weight: bold;
        letter-spacing: 1pt;
        text-align: center;
        padding: 5pt 0;
        z-index: 20;
    }
</style>
</head>
<body>
@php
    $member = $certificate->member;
    $watermark ??= false;
    $type = $certificate->type->value;
    $source = $certificate->sourceDescription();
    $date = $certificate->sourceDateRaw()?->format('F d, Y');

    $presented = match ($type) {
        'designation_order' => 'Be it known that',
        'completion' => 'is awarded to',
        default => 'is hereby presented to',
    };

    // "Issued this 5th of July 2025 ..." — the source (training/exam) date,
    // with an ordinal-day suffix. jS => "5th"; \o\f escapes the literal "of".
    $rawDate = $certificate->sourceDateRaw();
    $ordinalDay = $rawDate?->format('j');
    $suffix = match (true) {
        in_array((int)$ordinalDay % 100, [11, 12, 13]) => 'th',
        ((int)$ordinalDay % 10) === 1 => 'st',
        ((int)$ordinalDay % 10) === 2 => 'nd',
        ((int)$ordinalDay % 10) === 3 => 'rd',
        default => 'th',
    };
    $issuedLine = $rawDate
        ? 'Issued this '.$ordinalDay.'<sup>'.$suffix.'</sup> of '.$rawDate->format('F Y').' for whatever legal purpose it may serve.'
        : null;

    // The source/training title is pulled out of the sentence onto its own
    // line so it can carry the Mirante bold + CSC blue treatment the design
    // calls for, instead of being buried inline in body copy.
    [$lead, $trail] = match ($type) {
        'appearance' => [
            'for having personally appeared and participated in the',
            "conducted by the Civil Service Commission Regional Office VIII on {$date}.",
        ],
        'appreciation' => [
            'in grateful recognition of the invaluable service rendered as',
            "during the conduct of the Civil Service Examination on {$date}.",
        ],
        'completion' => [
            'for having successfully completed',
            "conducted onsite by the Civil Service Commission Regional Office VIII on {$date}.",
        ],
        'designation_order' => [
            'is hereby designated to serve in the',
            "on {$date}, subject to existing Civil Service Commission rules and regulations.",
        ],
    };
@endphp
<div class="sheet">
    @if ($letterheadDataUri)
        {{-- The letterhead already carries the CSC seal (top, below the
             ribbon) and the Lingkod Bayani mark + tagline (bottom, above the
             ribbon), so neither is drawn here. --}}
        <img class="letterhead-bg" src="{{ $letterheadDataUri }}" alt="">
    @else
        <div class="deco-outer"></div>
        <div class="deco-inner"></div>
    @endif

    <div class="content @unless($letterheadDataUri) plain @endunless">
        @unless ($letterheadDataUri)
            <p class="org">Republic of the Philippines &middot; Civil Service Commission</p>
            <p class="office">Regional Office VIII</p>
            <p class="program">PROCTAD — Professionalized Corps of Test Administrators</p>
        @endunless

        <p class="pretitle">This</p>
        <p class="title">{{ mb_strtoupper($certificate->type->label()) }}</p>
        <p class="lead">{{ $presented }}</p>
        <p class="name">{{ $member->nameFirstLast() }}</p>
        <div class="name-rule"></div>
        <p class="proctad-id">{{ $member->proctad_id }} &middot; {{ $member->fieldOffice?->name }}</p>
        <p class="lead">{{ $lead }}</p>
        <p class="source-title">{{ $source }}</p>
        <p class="trail">{{ $trail }}</p>
        @if ($issuedLine)
            <p class="issued">{!! $issuedLine !!}</p>
        @endif

        @if ($certificate->signatory_name)
            <div class="signatory {{ ($signatureDataUri ?? null) ? 'signatory-signed' : '' }}">
                @if ($signatureDataUri ?? null)
                    <img class="sig-image" src="{{ $signatureDataUri }}" alt="">
                @endif
                <p class="sig-name">{{ mb_strtoupper($certificate->signatory_name) }}</p>
                <p class="sig-pos">{{ $certificate->signatory_position }}</p>
            </div>
        @endif
    </div>

    <div class="cert-footer">
        @if ($qrDataUri)
            <img class="qr" src="{{ $qrDataUri }}" alt="Verification QR code">
        @endif
        <p class="no">{{ $certificate->certificate_no }}</p>
    </div>

    @if ($watermark)
        <div class="preview-banner">PREVIEW ONLY &mdash; NOT YET APPROVED OR ISSUED</div>
    @endif
</div>
</body>
</html>
