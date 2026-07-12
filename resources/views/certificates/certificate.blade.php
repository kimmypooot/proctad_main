<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $certificate->certificate_no }}</title>
<style>
    @page { margin: 0; }
    body {
        font-family: DejaVu Sans, sans-serif;
        margin: 0;
        color: #1e293b;
    }
    .frame {
        margin: 28px;
        border: 3px solid #1e3a8a;
        padding: 6px;
        height: 720px;
    }
    .frame.letterhead {
        margin: 0;
        border: none;
        padding: 0;
        height: 842px;
    }
    .inner {
        border: 1px solid #1e3a8a;
        height: 706px;
        padding: 40px 60px;
        text-align: center;
        position: relative;
    }
    .inner.letterhead {
        border: none;
        height: 842px;
        padding: 120px 90px 90px;
    }
    .letterhead-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .org { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #475569; }
    .office { font-size: 14px; font-weight: bold; margin-top: 2px; color: #1e3a8a; }
    .program { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #64748b; margin-top: 2px; }
    h1 { font-size: 34px; margin: 30px 0 6px; color: #1e3a8a; }
    .presented { font-size: 12px; color: #475569; margin-top: 18px; }
    .name { font-size: 26px; font-weight: bold; margin: 10px 0 2px; }
    .proctad-id { font-size: 11px; color: #1e3a8a; letter-spacing: 1px; }
    .body-text { font-size: 13px; line-height: 1.7; margin: 22px auto 0; width: 80%; color: #334155; }
    .signatory { position: absolute; bottom: 110px; right: 90px; text-align: center; }
    .signatory .sig-name { font-size: 14px; font-weight: bold; text-transform: uppercase; border-top: 1px solid #1e293b; padding-top: 4px; }
    .signatory .sig-pos { font-size: 11px; color: #475569; }
    .meta { position: absolute; bottom: 40px; left: 90px; text-align: left; font-size: 9px; color: #64748b; }
    .meta .no { font-size: 11px; color: #1e3a8a; font-weight: bold; }
    .qr { position: absolute; bottom: 40px; right: 90px; width: 70px; height: 70px; }
</style>
</head>
<body>
@php
    $member = $certificate->member;
    $typeLabel = $certificate->type->label();
    $body = match ($certificate->type->value) {
        'appearance' => "for having personally appeared and participated in the {$certificate->sourceDescription()} conducted by the Civil Service Commission Regional Office VIII on {$certificate->sourceDate()}.",
        'appreciation' => "in grateful recognition of the invaluable service rendered as {$certificate->sourceDescription()} during the conduct of the Civil Service Examination on {$certificate->sourceDate()}.",
        'completion' => "for having successfully completed the {$certificate->sourceDescription()} conducted by the Civil Service Commission Regional Office VIII on {$certificate->sourceDate()}.",
        'designation_order' => "is hereby designated to serve in the {$certificate->sourceDescription()} on {$certificate->sourceDate()}, subject to existing Civil Service Commission rules and regulations.",
    };
@endphp
<div class="frame @if($letterheadDataUri) letterhead @endif">
    <div class="inner @if($letterheadDataUri) letterhead @endif">
        @if ($letterheadDataUri)
            <img class="letterhead-bg" src="{{ $letterheadDataUri }}" alt="">
        @else
            {{-- No letterhead uploaded: self-contained header stands in for it. --}}
            <p class="org">Republic of the Philippines · Civil Service Commission</p>
            <p class="office">Regional Office VIII</p>
            <p class="program">PROCTAD — Professionalized Corps of Test Administrators</p>
        @endif

        <h1>{{ $typeLabel }}</h1>

        <p class="presented">
            {{ $certificate->type->value === 'designation_order' ? 'Be it known that' : 'is hereby presented to' }}
        </p>
        <p class="name">{{ $member->name }}</p>
        <p class="proctad-id">{{ $member->proctad_id }} · {{ $member->fieldOffice?->name }}</p>

        <p class="body-text">{{ $body }}</p>

        @if ($certificate->signatory_name)
            <div class="signatory">
                <p class="sig-name">{{ $certificate->signatory_name }}</p>
                <p class="sig-pos">{{ $certificate->signatory_position }}</p>
            </div>
        @endif

        <div class="meta">
            <p class="no">{{ $certificate->certificate_no }}</p>
            <p>Issued {{ $certificate->released_at?->format('F d, Y') }}</p>
            <p>Verify at {{ $verifyUrl }}</p>
        </div>

        @if ($qrDataUri)
            <img class="qr" src="{{ $qrDataUri }}" alt="Verification QR code">
        @endif
    </div>
</div>
</body>
</html>
