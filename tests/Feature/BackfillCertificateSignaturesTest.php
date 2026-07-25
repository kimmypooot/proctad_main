<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Signatory;
use App\Models\User;
use App\Notifications\CertificateReissued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillCertificateSignaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    /** A minimal valid 1x1 PNG so dompdf can decode the signature while rendering. */
    private function storeSignature(string $path): string
    {
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return $path;
    }

    private function releasedCertificate(string $signatoryName, ?string $signaturePath, string $no): Certificate
    {
        $assignment = ExamAssignment::factory()->create();

        return Certificate::create([
            'type' => CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => CertificateStatus::Released,
            'released_at' => now(),
            'certificate_no' => $no,
            'signatory_name' => $signatoryName,
            'signatory_position' => 'Director IV',
            'signatory_signature_path' => $signaturePath,
        ]);
    }

    public function test_backfills_signature_from_matching_signatory_even_when_inactive(): void
    {
        $path = $this->storeSignature('signatures/algas.png');
        // The signatory who signed the cert has since been deactivated — the exact
        // case Re-sign can't handle (it rejects inactive signatories).
        Signatory::create(['name' => 'Atty. Flordeliza C. Algas', 'position' => 'Director IV', 'active' => false, 'signature_path' => $path]);

        $certificate = $this->releasedCertificate('Atty. Flordeliza C. Algas', null, 'RO8-COA-2026-00001');

        $this->artisan('certificates:backfill-signatures')->assertSuccessful();

        $certificate->refresh();
        $this->assertSame($path, $certificate->signatory_signature_path);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_skips_certificates_whose_signatory_has_no_signature_file(): void
    {
        // Active, but no signature uploaded — nothing to attach.
        Signatory::create(['name' => 'Atty. Marilyn E. Taldo', 'position' => 'Director IV', 'active' => true, 'signature_path' => null]);

        $certificate = $this->releasedCertificate('Atty. Marilyn E. Taldo', null, 'RO8-COA-2026-00002');

        $this->artisan('certificates:backfill-signatures')
            ->expectsOutputToContain('Atty. Marilyn E. Taldo')
            ->assertSuccessful();

        $this->assertNull($certificate->fresh()->signatory_signature_path);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $path = $this->storeSignature('signatures/algas.png');
        Signatory::create(['name' => 'Atty. Flordeliza C. Algas', 'position' => 'Director IV', 'active' => false, 'signature_path' => $path]);

        $certificate = $this->releasedCertificate('Atty. Flordeliza C. Algas', null, 'RO8-COA-2026-00003');

        $this->artisan('certificates:backfill-signatures --dry-run')->assertSuccessful();

        $this->assertNull($certificate->fresh()->signatory_signature_path);
    }

    public function test_already_signed_certificates_are_left_untouched(): void
    {
        $existing = $this->storeSignature('signatures/existing.png');
        $other = $this->storeSignature('signatures/other.png');
        Signatory::create(['name' => 'Atty. Flordeliza C. Algas', 'position' => 'Director IV', 'active' => true, 'signature_path' => $other]);

        // Cert already carries a signature snapshot — the command only targets nulls.
        $certificate = $this->releasedCertificate('Atty. Flordeliza C. Algas', $existing, 'RO8-COA-2026-00004');

        $this->artisan('certificates:backfill-signatures')->assertSuccessful();

        $this->assertSame($existing, $certificate->fresh()->signatory_signature_path);
    }

    public function test_notify_option_notifies_the_member(): void
    {
        Notification::fake();

        $path = $this->storeSignature('signatures/algas.png');
        Signatory::create(['name' => 'Atty. Flordeliza C. Algas', 'position' => 'Director IV', 'active' => false, 'signature_path' => $path]);

        $certificate = $this->releasedCertificate('Atty. Flordeliza C. Algas', null, 'RO8-COA-2026-00005');
        $member = User::factory()->create(['role' => UserRole::Member]);
        $certificate->member->update(['user_id' => $member->id]);

        $this->artisan('certificates:backfill-signatures')->assertSuccessful();
        Notification::assertNothingSent();

        // A second cert for the same member, backfilled with --notify this time.
        $certificate2 = $this->releasedCertificate('Atty. Flordeliza C. Algas', null, 'RO8-COA-2026-00006');
        $certificate2->member->update(['user_id' => $member->id]);

        $this->artisan('certificates:backfill-signatures --notify')->assertSuccessful();
        Notification::assertSentTo($member, CertificateReissued::class);
    }
}
