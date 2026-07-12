<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\NonExamPersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NonExamPersonnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_and_is_forbidden_for_members(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        NonExamPersonnel::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/non-exam-personnel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NonExamPersonnel/Index')
                ->has('personnel.data', 2));

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/non-exam-personnel')->assertForbidden();
    }

    public function test_admin_can_register_personnel_with_auto_generated_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/non-exam-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'is_active' => true,
        ])->assertRedirect();

        $nep = NonExamPersonnel::firstOrFail();
        $this->assertStringStartsWith('NEP-CSCRO8-', $nep->nep_id);
        $this->assertSame('JUAN DELA CRUZ', $nep->name);
    }

    public function test_fo_admin_cannot_register_personnel_for_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $this->actingAs($foAdmin)->post('/non-exam-personnel', [
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
        $nep = NonExamPersonnel::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)->get("/non-exam-personnel/{$nep->id}")->assertForbidden();
    }

    public function test_photo_upload_and_retrieval(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/non-exam-personnel', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'personnel_type' => 'janitor',
            'field_office_id' => $fo->id,
            'is_active' => true,
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect();

        $nep = NonExamPersonnel::firstOrFail();
        Storage::disk('local')->assertExists($nep->photo_path);

        $this->actingAs($admin)->get("/non-exam-personnel/{$nep->id}/photo")->assertOk();
    }

    public function test_show_page_includes_id_card_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $nep = NonExamPersonnel::factory()->create();

        $this->actingAs($admin)
            ->get("/non-exam-personnel/{$nep->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NonExamPersonnel/Show')
                ->where('idCard.nep_id', $nep->nep_id)
                ->where('idCard.qr_value', "NEP:{$nep->nep_id}"));
    }

    public function test_admin_can_update_and_delete_personnel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $nep = NonExamPersonnel::factory()->create();

        $this->actingAs($admin)->put("/non-exam-personnel/{$nep->id}", [
            'first_name' => $nep->first_name,
            'last_name' => $nep->last_name,
            'sex' => $nep->sex,
            'personnel_type' => 'coordinator',
            'field_office_id' => $nep->field_office_id,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('coordinator', $nep->fresh()->personnel_type->value);
        $this->assertFalse($nep->fresh()->is_active);

        $this->actingAs($admin)->delete("/non-exam-personnel/{$nep->id}")->assertRedirect();
        $this->assertSoftDeleted($nep);
    }
}
