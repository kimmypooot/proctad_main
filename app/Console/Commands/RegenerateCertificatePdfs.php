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
    protected $signature = 'certificates:regenerate-pdfs {--notify : Also bell-notify every affected member}';

    protected $description = 'Re-render and overwrite every released certificate\'s stored PDF';

    public function handle(CertificateService $service): int
    {
        $certificates = Certificate::whereNotNull('released_at')->get();
        $notify = (bool) $this->option('notify');

        // Notification is opt-in here, unlike the single-certificate action in
        // the UI: this runs across every certificate ever issued, and firing
        // thousands of unexpected notifications for a routine letterhead change
        // is worse than silence. Pass --notify when the visible change is one
        // members genuinely need to know about.
        $this->withProgressBar($certificates, function (Certificate $certificate) use ($service, $notify) {
            $service->regeneratePdf($certificate, notifyMember: $notify);
        });

        $this->newLine(2);
        $this->info("Regenerated {$certificates->count()} certificate PDF(s).");
        $this->line($notify
            ? 'Affected members were notified.'
            : 'Members were not notified. Re-run with --notify if they should be.');

        return self::SUCCESS;
    }
}
