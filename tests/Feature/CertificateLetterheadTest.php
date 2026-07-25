<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Letterhead;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateLetterheadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    private function pendingCertificate(): Certificate
    {
        $assignment = ExamAssignment::factory()->create();

        return Certificate::create([
            'type' => CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => CertificateStatus::Pending,
            'requested_by' => User::factory()->create()->id,
        ]);
    }

    public function test_certificate_renders_without_a_letterhead(): void
    {
        $certificate = $this->pendingCertificate();

        app(CertificateService::class)->release($certificate, User::factory()->create(['role' => UserRole::FieldDirector]));

        $certificate->refresh();
        $this->assertSame(CertificateStatus::Released, $certificate->status);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_certificate_composites_the_active_letterhead(): void
    {
        $letterhead = Letterhead::factory()->create(['is_active' => true, 'file_path' => 'letterheads/active.png']);
        // A minimal valid 1x1 PNG so dompdf can decode it while compositing.
        $onePixelPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('local')->put($letterhead->file_path, $onePixelPng);

        $certificate = $this->pendingCertificate();

        app(CertificateService::class)->release($certificate, User::factory()->create(['role' => UserRole::FieldDirector]));

        $certificate->refresh();
        $this->assertSame(CertificateStatus::Released, $certificate->status);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_missing_letterhead_file_falls_back_gracefully(): void
    {
        Letterhead::factory()->create(['is_active' => true, 'file_path' => 'letterheads/missing.png']);

        $certificate = $this->pendingCertificate();

        app(CertificateService::class)->release($certificate, User::factory()->create(['role' => UserRole::FieldDirector]));

        $this->assertSame(CertificateStatus::Released, $certificate->fresh()->status);
    }

    /**
     * The signature image must actually reach the certificate, and only overlay
     * the name when one is present — the wiring behind the "unsigned" case that
     * previously shipped blank certificates.
     */
    public function test_signature_overlays_the_name_only_when_one_is_present(): void
    {
        $certificate = $this->pendingCertificate();
        $certificate->update([
            'signatory_name' => 'Atty. Jane D. Reyes',
            'signatory_position' => 'Director IV',
            'certificate_no' => 'RO8-COA-2026-00001',
        ]);
        $certificate->loadMissing('member.fieldOffice', 'certifiable');

        $vars = [
            'certificate' => $certificate,
            'qrDataUri' => null,
            'verifyUrl' => 'https://example.test/verify',
            'letterheadDataUri' => null,
            'watermark' => false,
            'fontDir' => resource_path('fonts'),
        ];

        // Assert on the class actually applied to the signatory div (the class
        // name alone always appears in the <style> block, so it can't discriminate).
        $signed = view('certificates.certificate', [...$vars, 'signatureDataUri' => 'data:image/png;base64,AAAA'])->render();
        $this->assertStringContainsString('class="sig-image"', $signed);
        $this->assertStringContainsString('class="signatory signatory-signed"', $signed);

        $unsigned = view('certificates.certificate', [...$vars, 'signatureDataUri' => null])->render();
        $this->assertStringNotContainsString('class="sig-image"', $unsigned);
        // No overlay class on an unsigned cert — the name keeps its full gap.
        $this->assertStringNotContainsString('class="signatory signatory-signed"', $unsigned);
    }
}
