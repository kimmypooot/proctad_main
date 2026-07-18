<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\OtherExaminationPersonnel;
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

    public function test_admin_can_register_personnel_with_auto_generated_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'is_active' => true,
        ])->assertRedirect();

        $oep = OtherExaminationPersonnel::firstOrFail();
        $this->assertStringStartsWith('OEP-CSCRO8-', $oep->oep_id);
        $this->assertSame('DELA CRUZ, JUAN', $oep->name);
    }

    public function test_fo_admin_cannot_register_personnel_for_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $otherFo->id,
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
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/other-examination-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
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
            'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('coordinator', $oep->fresh()->personnel_type->value);
        $this->assertFalse($oep->fresh()->is_active);

        $this->actingAs($admin)->delete("/other-examination-personnel/{$oep->id}")->assertRedirect();
        $this->assertSoftDeleted($oep);
    }
}
