<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Inertia\Inertia;
use Inertia\Response;

class VerifyCertificateController extends Controller
{
    /**
     * Public certificate verification via QR (spec 2.4 / 5).
     */
    public function __invoke(string $certificateNo): Response
    {
        $certificate = Certificate::with('member:id,proctad_id,first_name,middle_name,last_name,suffix', 'fieldOffice:id,name')
            ->where('certificate_no', $certificateNo)
            ->where('status', CertificateStatus::Released)
            ->first();

        return Inertia::render('VerifyCertificate', [
            'result' => $certificate ? [
                'certificate_no' => $certificate->certificate_no,
                'type_label' => $certificate->type->label(),
                'member_name' => $certificate->member->name,
                'proctad_id' => $certificate->member->proctad_id,
                'field_office' => $certificate->fieldOffice?->name,
                'source' => $certificate->sourceDescription(),
                'source_date' => $certificate->sourceDate(),
                'released_at' => $certificate->released_at?->format('F d, Y'),
            ] : null,
            'code' => $certificateNo,
        ]);
    }
}
