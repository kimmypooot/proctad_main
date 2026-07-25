<?php

namespace App\Console\Commands;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\Signatory;
use App\Services\CertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Attach a signature image to already-released certificates that were issued
 * before their signatory had one on file.
 *
 * A certificate snapshots its signatory's signature path at release (spec 2.3),
 * and neither Regenerate (keeps the frozen snapshot) nor Re-sign (needs an
 * ACTIVE signatory and would rewrite the printed name) can fill in a signature
 * that simply didn't exist yet at issue time. This backfills the gap: for each
 * released certificate with no signature snapshot, it finds the signatory whose
 * name is ALREADY printed on the certificate — active or not — and, if that
 * signatory now has a signature file, re-signs the certificate with it. The
 * printed name never changes because it's matched from the name already there.
 */
class BackfillCertificateSignatures extends Command
{
    protected $signature = 'certificates:backfill-signatures {--dry-run : List what would change without writing} {--notify : Bell-notify each affected member}';

    protected $description = 'Attach signatures to released certificates issued before their signatory had one on file';

    public function handle(CertificateService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Certificate::where('status', CertificateStatus::Released)
            ->whereNull('signatory_signature_path')
            ->whereNotNull('signatory_name')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No released certificates are missing a signature snapshot.');

            return self::SUCCESS;
        }

        $backfilled = 0;
        $missing = [];

        foreach ($candidates as $certificate) {
            $signatory = $this->matchingSignatory($certificate->signatory_name);

            if ($signatory === null) {
                // No signatory record by that name has a usable signature file yet
                // — e.g. the region-wide signatory whose signature is still not
                // uploaded. Nothing to attach; report it so it isn't silently lost.
                $missing[$certificate->signatory_name] = ($missing[$certificate->signatory_name] ?? 0) + 1;

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."{$certificate->certificate_no}  <-  {$signatory->name}");

            if (! $dryRun) {
                $service->resign($certificate, $signatory);

                if ($this->option('notify')) {
                    $certificate->loadMissing('member.user');
                    $certificate->member?->user?->notify(new \App\Notifications\CertificateReissued($certificate));
                }
            }

            $backfilled++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would backfill ' : 'Backfilled ')."{$backfilled} certificate(s).");

        foreach ($missing as $name => $count) {
            $this->warn("Skipped {$count} cert(s) signed by \"{$name}\" — no signature uploaded for that signatory yet.");
        }

        return self::SUCCESS;
    }

    /**
     * The signatory whose name matches the one printed on the certificate and
     * who now has a signature file on disk. Active status is ignored on purpose:
     * a certificate issued under a signatory who has since been deactivated must
     * still be able to show that person's signature.
     */
    private function matchingSignatory(string $name): ?Signatory
    {
        return Signatory::where('name', $name)
            ->whereNotNull('signature_path')
            ->orderByDesc('active')
            ->get()
            ->first(fn (Signatory $s) => Storage::disk('local')->exists($s->signature_path));
    }
}
