<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatoryTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $leyte;

    private FieldOffice $samar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
    }

    private function staff(UserRole $role, ?FieldOffice $office = null): User
    {
        return User::factory()->create(['role' => $role, 'field_office_id' => $office?->id]);
    }

    public function test_fo_admin_manages_only_own_field_office_signatories(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $ownEntry = Signatory::create(['field_office_id' => $this->leyte->id, 'name' => 'A', 'position' => 'Director II', 'active' => true]);
        $otherEntry = Signatory::create(['field_office_id' => $this->samar->id, 'name' => 'B', 'position' => 'Director II', 'active' => true]);
        $regionEntry = Signatory::create(['field_office_id' => null, 'name' => 'C', 'position' => 'Director IV', 'active' => true]);

        $this->actingAs($admin)->post('/signatories', [
            'name' => 'New Leyte Signatory', 'position' => 'Director II',
            'active' => true, 'field_office_id' => $this->leyte->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/signatories', [
            'name' => 'Bad', 'position' => 'Director II',
            'active' => true, 'field_office_id' => $this->samar->id,
        ])->assertSessionHasErrors('field_office_id');

        $this->actingAs($admin)->put("/signatories/{$ownEntry->id}", [
            'name' => 'A Updated', 'position' => 'Director II',
            'active' => true, 'field_office_id' => $this->leyte->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete("/signatories/{$otherEntry->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/signatories/{$regionEntry->id}")->assertForbidden();
    }

    public function test_esd_admin_manages_any_scope_and_member_is_blocked(): void
    {
        $esd = $this->staff(UserRole::EsdAdmin);

        $this->actingAs($esd)->post('/signatories', [
            'name' => 'Region Default', 'position' => 'Director IV',
            'active' => true, 'field_office_id' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('signatories', ['name' => 'Region Default', 'field_office_id' => null]);

        $this->actingAs($this->staff(UserRole::Member))->get('/signatories')->assertForbidden();
    }

    public function test_current_for_prefers_fo_specific_active_signatory(): void
    {
        $region = Signatory::create(['field_office_id' => null, 'name' => 'Region', 'position' => 'Director IV', 'active' => true]);
        $inactive = Signatory::create(['field_office_id' => $this->leyte->id, 'name' => 'Old FO', 'position' => 'Director II', 'active' => false]);

        // No active FO entry yet: falls back to the region-wide default.
        $this->assertSame($region->id, Signatory::currentFor($this->leyte->id)?->id);

        $foEntry = Signatory::create(['field_office_id' => $this->leyte->id, 'name' => 'FO', 'position' => 'Director II', 'active' => true]);

        $this->assertSame($foEntry->id, Signatory::currentFor($this->leyte->id)?->id);
        $this->assertSame($region->id, Signatory::currentFor($this->samar->id)?->id);
        $this->assertSame($region->id, Signatory::currentFor(null)?->id);
    }

    public function test_signature_image_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');

        $admin = $this->staff(UserRole::EsdAdmin);

        $this->actingAs($admin)->post('/signatories', [
            'name' => 'Atty. Juana D. Reyes',
            'position' => 'Director IV',
            'field_office_id' => null,
            'active' => true,
            'signature' => UploadedFile::fake()->image('sig.png', 600, 200),
        ])->assertRedirect();

        $signatory = Signatory::firstOrFail();
        $this->assertNotNull($signatory->signature_path);
        Storage::disk('local')->assertExists($signatory->signature_path);

        $storedPath = $signatory->signature_path;

        $this->actingAs($admin)->post("/signatories/{$signatory->id}", [
            '_method' => 'put',
            'name' => $signatory->name,
            'position' => $signatory->position,
            'field_office_id' => null,
            'active' => true,
            'remove_signature' => true,
        ])->assertRedirect();

        $this->assertNull($signatory->fresh()->signature_path);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_non_png_signature_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->post('/signatories', [
                'name' => 'Atty. Juana D. Reyes',
                'position' => 'Director IV',
                'field_office_id' => null,
                'active' => true,
                // JPEG has no alpha channel — it would stamp a white box over
                // the printed name it's supposed to overlay.
                'signature' => UploadedFile::fake()->image('sig.jpg', 600, 200),
            ])
            ->assertSessionHasErrors('signature');
    }

    /**
     * The signature is snapshotted at release like name/position. Replacing a
     * signatory's image must not re-sign certificates already issued — which
     * matters doubly because regeneratePdf() overwrites the stored PDF in place.
     */
    public function test_replacing_a_signature_does_not_alter_already_issued_certificates(): void
    {
        Storage::fake('local');

        $admin = $this->staff(UserRole::EsdAdmin);
        $signatory = Signatory::create([
            'field_office_id' => null,
            'name' => 'Atty. Juana D. Reyes',
            'position' => 'Director IV',
            'signature_path' => 'signatures/original.png',
            'active' => true,
        ]);
        Storage::disk('local')->put('signatures/original.png', 'original-bytes');

        $assignment = \App\Models\ExamAssignment::factory()->create();
        $certificate = \App\Models\Certificate::create([
            'type' => \App\Enums\CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => \App\Models\ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => \App\Enums\CertificateStatus::Pending,
            'requested_by' => $admin->id,
        ]);

        app(\App\Services\CertificateService::class)->release($certificate, $admin);

        $this->assertSame('signatures/original.png', $certificate->fresh()->signatory_signature_path);

        // Swap the signatory's image for a new one.
        $this->actingAs($admin)->post("/signatories/{$signatory->id}", [
            '_method' => 'put',
            'name' => $signatory->name,
            'position' => $signatory->position,
            'field_office_id' => null,
            'active' => true,
            'signature' => UploadedFile::fake()->image('new.png', 600, 200),
        ])->assertRedirect();

        $this->assertNotSame('signatures/original.png', $signatory->fresh()->signature_path);

        // The issued certificate still points at the original, and the file it
        // depends on was not deleted out from under it.
        $this->assertSame('signatures/original.png', $certificate->fresh()->signatory_signature_path);
        Storage::disk('local')->assertExists('signatures/original.png');
    }
}
