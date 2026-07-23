<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\School;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TestingCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_any_testing_center(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/testing-centers', [
            'name' => 'Tacloban City',
            'field_office_id' => $fo->id,
            'is_active' => true,
        ])->assertRedirect();

        $center = TestingCenter::firstOrFail();
        $this->assertSame('Tacloban City', $center->name);
        // The center is linked to the field office it was added under.
        $this->assertTrue($center->fieldOffices()->whereKey($fo->id)->exists());

        $this->actingAs($admin)->put("/testing-centers/{$center->id}", [
            'name' => 'Tacloban',
            'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('Tacloban', $center->fresh()->name);

        $this->actingAs($admin)->delete("/testing-centers/{$center->id}")->assertRedirect();
        $this->assertModelMissing($center);
    }

    public function test_fo_admin_cannot_create_center_outside_own_field_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->post('/testing-centers', [
            'name' => 'Elsewhere City',
            'field_office_id' => $otherFo->id,
            'is_active' => true,
        ])->assertSessionHasErrors('field_office_id');

        $this->assertSame(0, TestingCenter::count());
    }

    public function test_linking_a_shared_center_adds_the_offices_staff_to_it(): void
    {
        $samar = FieldOffice::factory()->create();
        $easternSamar = FieldOffice::factory()->create();

        // Samar already handles the center; its 2 staff are linked on save.
        $center = TestingCenter::factory()->forFieldOffice($samar)->create();
        User::factory()->count(2)->create(['role' => UserRole::FoAdmin, 'field_office_id' => $samar->id]);

        // Eastern Samar's 3 staff exist before it handles any center.
        $easternStaff = User::factory()->count(3)->create(['role' => UserRole::FoAdmin, 'field_office_id' => $easternSamar->id]);

        // Eastern Samar joins the shared center (the "Link existing" flow).
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->post('/testing-centers', [
            'field_office_id' => $easternSamar->id,
            'testing_center_id' => $center->id,
        ])->assertRedirect();

        // The center's staff pool now spans both offices, and Eastern Samar's
        // existing staff were attached even though no user record was re-saved.
        $this->assertSame(5, $center->fresh()->users()->count());
        foreach ($easternStaff as $staff) {
            $this->assertTrue($staff->testingCenters()->whereKey($center->id)->exists());
        }
    }

    public function test_center_with_schools_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $center = TestingCenter::factory()->create();
        School::factory()->create(['testing_center_id' => $center->id]);

        $this->actingAs($admin)->delete("/testing-centers/{$center->id}")
            ->assertRedirect();

        $this->assertModelExists($center);
    }

    public function test_a_user_is_linked_to_their_field_offices_testing_centers_on_save(): void
    {
        $fo = FieldOffice::factory()->create();
        $center = TestingCenter::factory()->forFieldOffice($fo)->create();

        // The UserObserver derives the pivot from the field office on save, so no
        // manual attach is needed — and both directions resolve through it.
        $user = User::factory()->create(['field_office_id' => $fo->id]);

        $this->assertTrue($user->testingCenters()->whereKey($center->id)->exists());
        $this->assertTrue($center->users()->whereKey($user->id)->exists());
    }

    public function test_fo_admin_only_sees_own_field_offices_centers(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        TestingCenter::factory()->forFieldOffice($fo)->create(['name' => 'Mine City']);
        TestingCenter::factory()->forFieldOffice($otherFo)->create(['name' => 'Theirs City']);

        $this->actingAs($foAdmin)
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Locations/Index')
                ->has('testingCenters', 1)
                ->where('testingCenters.0.name', 'Mine City')
                ->where('scope.field_office_scoped', true)
            );
    }
}
