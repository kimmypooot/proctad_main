<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_visible_to_esd_admin_but_not_fo_admin_or_members(): void
    {
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        FieldOffice::factory()->count(2)->create();

        $this->actingAs($esdAdmin)
            ->get('/field-offices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/FieldOffices/Index')
                ->has('fieldOffices', 2)
                ->where('can.manage', true));

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $this->actingAs($foAdmin)->get('/field-offices')->assertForbidden();

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/field-offices')->assertForbidden();
    }

    public function test_super_admin_can_create_and_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/field-offices', [
            'name' => 'Leyte Field Office',
            'code' => 'LEY',
            'address' => 'Palo, Leyte',
            'is_active' => true,
        ])->assertRedirect();

        $office = FieldOffice::firstOrFail();
        $this->assertSame('Leyte Field Office', $office->name);

        $this->actingAs($admin)->put("/field-offices/{$office->id}", [
            'name' => 'Leyte Field Office (Renamed)',
            'code' => 'LEY',
            'address' => 'Tacloban City, Leyte',
            'is_active' => true,
        ])->assertRedirect();

        $office->refresh();
        $this->assertSame('Leyte Field Office (Renamed)', $office->name);
        $this->assertSame('Tacloban City, Leyte', $office->address);
    }

    public function test_fo_admin_cannot_create_field_office(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($foAdmin)->post('/field-offices', [
            'name' => 'Leyte Field Office',
            'code' => 'LEY',
        ])->assertForbidden();
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        FieldOffice::factory()->create(['code' => 'LEY']);

        $this->actingAs($admin)->post('/field-offices', [
            'name' => 'Another Office',
            'code' => 'LEY',
        ])->assertSessionHasErrors('code');
    }
}
