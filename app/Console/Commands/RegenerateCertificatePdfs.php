<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Console\Command;

/**
 * Re-renders every released certificate's stored PDF — needed after
 * changing the active letterhead or certificate template geometry, since
 * `pdf_path` is otherwise only (re)written on release/resign.
 */
class RegenerateCertificatePdfs extends Command
{
    protected $signature = 'certificates:regenerate-pdfs';

    protected $description = 'Re-render and overwrite every released certificate\'s stored PDF';

    public function handle(CertificateService $service): int
    {
        $certificates = Certificate::whereNotNull('released_at')->get();

        $this->withProgressBar($certificates, function (Certificate $certificate) use ($service) {
            $service->regeneratePdf($certificate);
        });

        $this->newLine(2);
        $this->info("Regenerated {$certificates->count()} certificate PDF(s).");

        return self::SUCCESS;
    }
}
