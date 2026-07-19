<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VerifyController extends Controller
{
    /**
     * Public QR verification page — limited info only (spec 2.4).
     */
    public function __invoke(string $proctadId): Response
    {
        // Legacy printed QR stock encoded "{proctad_id}|attendance" (raw text,
        // not a URL, so it never actually linked here) — strip the suffix
        // defensively in case such a code is ever pasted/fed into this route,
        // matching ScannerController::normalize()'s compatibility handling.
        $proctadId = strtoupper(trim(Str::before($proctadId, '|')));

        $member = Member::with('fieldOffice:id,name')
            ->where('proctad_id', $proctadId)
            ->first();

        return Inertia::render('Verify', [
            'result' => $member ? [
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'field_office' => $member->fieldOffice?->name,
                'status' => $member->status->value,
                'status_label' => $member->status->label(),
                'status_variant' => $member->status->badgeVariant(),
            ] : null,
            'code' => $proctadId,
            // Shown on the result so whoever is checking a presented ID can see
            // this is a live lookup, not a cached or screenshotted page.
            'verifiedAt' => now()->format('F j, Y \a\t g:i A'),
        ]);
    }
}
