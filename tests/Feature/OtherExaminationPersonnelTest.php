<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\OtherExaminationPersonnel;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OtherExaminationPersonnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_and_is_forbidden_for_members(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        OtherExaminationPersonnel::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/other-examination-personnel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OtherExaminationPersonnel/Index')
                ->has('personnel.data', 2));

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/other-examination-personnel')->assertForbidden();
    }

    /** An office and a center it handles — the pairing every placed record needs. */
    private function officeWithCenter(): array
    {
        $office = FieldOffice::factory()->create();

        return [$office, TestingCenter::factory()->forFieldOffice($office)->create()];
    }

    public function test_admin_can_register_personnel_with_auto_generated_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$fo, $center] = $this->officeWithCenter();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'testing_center_id' => $center->id,
            'is_active' => true,
        ])->assertRedirect();

        $oep = OtherExaminationPersonnel::firstOrFail();
        $this->assertStringStartsWith('OEP-CSCRO8-', $oep->oep_id);
        $this->assertSame('DELA CRUZ, JUAN', $oep->name);
        $this->assertSame($center->id, $oep->testing_center_id);
    }

    public function test_fo_admin_cannot_register_personnel_for_another_office(): void
    {
        [$fo, $center] = $this->officeWithCenter();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $otherFo->id,
            'testing_center_id' => $center->id,
            'is_active' => true,
        ])->assertSessionHasErrors('field_office_id');
    }

    public function test_field_office_and_testing_center_are_required(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$fo] = $this->officeWithCenter();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'is_active' => true,
        ])->assertSessionHasErrors(['field_office_id', 'testing_center_id']);

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'is_active' => true,
        ])->assertSessionHasErrors('testing_center_id');
    }

    public function test_testing_center_must_be_handled_by_the_chosen_office(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$fo] = $this->officeWithCenter();
        [, $unrelatedCenter] = $this->officeWithCenter();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'testing_center_id' => $unrelatedCenter->id,
            'is_active' => true,
        ])->assertSessionHasErrors('testing_center_id');
    }

    /**
     * Two offices sharing a city (Leyte I and Leyte II both serve Tacloban)
     * must each see the personnel working there, whoever engaged them — which
     * is the whole reason jurisdiction is drawn by center rather than by office.
     */
    public function test_staff_of_a_sibling_office_see_personnel_in_the_shared_center(): void
    {
        [$leyteOne, $tacloban] = $this->officeWithCenter();
        $leyteTwo = FieldOffice::factory()->create();
        $tacloban->fieldOffices()->syncWithoutDetaching([$leyteTwo->id]);

        $oep = OtherExaminationPersonnel::factory()->create([
            'field_office_id' => $leyteOne->id,
            'testing_center_id' => $tacloban->id,
        ]);

        $siblingAdmin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $leyteTwo->id,
        ]);

        $this->assertTrue($oep->isWithinJurisdictionOf($siblingAdmin));

        $this->actingAs($siblingAdmin)
            ->get('/other-examination-personnel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('personnel.data', 1));
    }

    /**
     * Region-wide is the regional office, not a blank field: RO8 personnel are
     * drawn on by every office, the way regional-office members are.
     */
    public function test_fo_admin_sees_and_can_act_on_regional_office_personnel(): void
    {
        [$fo, $center] = $this->officeWithCenter();
        $regional = FieldOffice::factory()->regional()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $own = OtherExaminationPersonnel::factory()->create([
            'field_office_id' => $fo->id,
            'testing_center_id' => $center->id,
        ]);
        // Regional-office personnel carry no center: they serve every city.
        $regionWide = OtherExaminationPersonnel::factory()->create([
            'field_office_id' => $regional->id,
            'testing_center_id' => null,
        ]);
        OtherExaminationPersonnel::factory()->create(['field_office_id' => FieldOffice::factory()]);

        $this->actingAs($foAdmin)
            ->get('/other-examination-personnel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('personnel.data', 2));

        $this->actingAs($foAdmin)
            ->getJson("/other-examination-personnel/{$regionWide->id}/details")
            ->assertOk();

        $this->assertTrue($regionWide->isWithinJurisdictionOf($foAdmin));
        $this->assertTrue($own->isWithinJurisdictionOf($foAdmin));
    }

    public function test_fo_admin_cannot_create_region_wide_personnel(): void
    {
        [$fo] = $this->officeWithCenter();
        $regional = FieldOffice::factory()->regional()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $regional->id,
            'is_active' => true,
        ])->assertSessionHasErrors('field_office_id');
    }

    public function test_fo_admin_cannot_view_personnel_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $oep = OtherExaminationPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)->get("/other-examination-personnel/{$oep->id}")->assertForbidden();
    }

    public function test_photo_upload_and_retrieval(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        [$fo, $center] = $this->officeWithCenter();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'testing_center_id' => $center->id,
            'is_active' => true,
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect();

        $oep = OtherExaminationPersonnel::firstOrFail();
        Storage::disk('local')->assertExists($oep->photo_path);

        $this->actingAs($admin)->get("/other-examination-personnel/{$oep->id}/photo")->assertOk();
    }

    public function test_show_redirects_to_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $oep = OtherExaminationPersonnel::factory()->create();

        $this->actingAs($admin)
            ->get("/other-examination-personnel/{$oep->id}")
            ->assertRedirect('/other-examination-personnel');
    }

    public function test_details_endpoint_includes_id_card_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $oep = OtherExaminationPersonnel::factory()->create();

        $this->actingAs($admin)
            ->getJson("/other-examination-personnel/{$oep->id}/details")
            ->assertOk()
            ->assertJsonPath('idCard.oep_id', $oep->oep_id)
            ->assertJsonPath('idCard.qr_value', "OEP:{$oep->oep_id}");
    }

    public function test_admin_can_update_and_delete_personnel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $oep = OtherExaminationPersonnel::factory()->create();

        $this->actingAs($admin)->put("/other-examination-personnel/{$oep->id}", [
            'first_name' => $oep->first_name,
            'last_name' => $oep->last_name,
            'sex' => $oep->sex,
            'personnel_type' => 'coordinator',
            'field_office_id' => $oep->field_office_id,
            'testing_center_id' => $oep->testing_center_id,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('coordinator', $oep->fresh()->personnel_type->value);
        $this->assertFalse($oep->fresh()->is_active);

        $this->actingAs($admin)->delete("/other-examination-personnel/{$oep->id}")->assertRedirect();
        $this->assertSoftDeleted($oep);
    }
}
