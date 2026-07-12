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
}
