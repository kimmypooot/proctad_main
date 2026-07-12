<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateBulkResignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    private function releasedCertificate(): Certificate
    {
        $assignment = ExamAssignment::factory()->create();

        return Certificate::create([
            'type' => CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => CertificateStatus::Released,
            'certificate_no' => 'RO8-COA-2026-00001',
            'signatory_name' => 'Old Signatory',
            'signatory_position' => 'Old Position',
        ]);
    }

    public function test_super_admin_can_bulk_resign_released_certificates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $certificate = $this->releasedCertificate();
        $signatory = Signatory::create(['name' => 'New Signatory', 'position' => 'Director IV', 'active' => true]);

        $this->actingAs($admin)->post('/certificates/bulk-resign', [
            'certificate_ids' => [$certificate->id],
            'signatory_id' => $signatory->id,
        ])->assertRedirect();

        $certificate->refresh();
        $this->assertSame('New Signatory', $certificate->signatory_name);
        $this->assertSame('Director IV', $certificate->signatory_position);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_pending_certificates_are_skipped_not_resigned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $assignment = ExamAssignment::factory()->create();
        $pending = Certificate::create([
            'type' => CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => CertificateStatus::Pending,
        ]);
        $signatory = Signatory::create(['name' => 'Test Signatory', 'position' => 'Director', 'active' => true]);

        $this->actingAs($admin)->post('/certificates/bulk-resign', [
            'certificate_ids' => [$pending->id],
            'signatory_id' => $signatory->id,
        ])->assertSessionHas('success');

        $this->assertNull($pending->fresh()->signatory_name);
    }

    public function test_inactive_signatory_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $certificate = $this->releasedCertificate();
        $signatory = Signatory::create(['name' => 'Test Signatory', 'position' => 'Director', 'active' => false]);

        $this->actingAs($admin)->post('/certificates/bulk-resign', [
            'certificate_ids' => [$certificate->id],
            'signatory_id' => $signatory->id,
        ])->assertSessionHasErrors('signatory_id');
    }

    public function test_only_super_admin_can_bulk_resign(): void
    {
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $certificate = $this->releasedCertificate();
        $signatory = Signatory::create(['name' => 'Test Signatory', 'position' => 'Director', 'active' => true]);

        $this->actingAs($esd)->post('/certificates/bulk-resign', [
            'certificate_ids' => [$certificate->id],
            'signatory_id' => $signatory->id,
        ])->assertForbidden();

        $this->assertSame('Old Signatory', $certificate->fresh()->signatory_name);
    }
}
