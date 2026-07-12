<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
