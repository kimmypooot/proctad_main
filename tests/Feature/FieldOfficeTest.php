<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_counts_staff_at_shared_testing_centers(): void
    {
        $leyte1 = FieldOffice::factory()->create(['name' => 'Leyte I']);
        $leyte2 = FieldOffice::factory()->create(['name' => 'Leyte II']);

        // One shared center; four staff sit under Leyte II, none under Leyte I.
        $center = TestingCenter::factory()->create(['name' => 'Tacloban City']);
        $center->fieldOffices()->attach([$leyte1->id, $leyte2->id]);
        User::factory()->count(4)->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte2->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        // Both offices report the shared pool of four, not 0 vs 4.
        $this->actingAs($admin)
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fieldOffices.0.name', 'Leyte I')
                ->where('fieldOffices.0.users_count', 4)
                ->where('fieldOffices.1.name', 'Leyte II')
                ->where('fieldOffices.1.users_count', 4)
                ->where('testingCenters.0.users_count', 4));
    }

    public function test_locations_shows_field_offices_and_gates_management(): void
    {
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        FieldOffice::factory()->count(2)->create();

        // Admins browse every field office and may manage them.
        $this->actingAs($esdAdmin)
            ->get('/locations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Locations/Index')
                ->has('fieldOffices', 2)
                ->where('can.manageFieldOffices', true));

        // Field-office staff can browse Locations but not manage field offices.
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $this->actingAs($foAdmin)
            ->get('/locations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.manageFieldOffices', false));

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/locations')->assertForbidden();
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

    public function test_super_admin_can_delete_an_empty_field_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();

        $this->actingAs($admin)->delete("/field-offices/{$office->id}")->assertRedirect();

        $this->assertModelMissing($office);
    }

    public function test_field_office_with_dependents_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $office = FieldOffice::factory()->create();
        // Members anchor the office, so deletion is refused (testing-center links
        // just detach and never block).
        \App\Models\Member::factory()->create(['field_office_id' => $office->id]);

        $this->actingAs($admin)->delete("/field-offices/{$office->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($office);
    }

    public function test_fo_admin_cannot_delete_field_office(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $office = FieldOffice::factory()->create();

        $this->actingAs($foAdmin)->delete("/field-offices/{$office->id}")->assertForbidden();
    }
}
