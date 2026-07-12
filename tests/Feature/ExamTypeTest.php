<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamType;
use App\Models\Examination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_visible_to_field_office_staff_but_not_members(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        ExamType::factory()->count(2)->create();

        $this->actingAs($foAdmin)
            ->get('/exam-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/ExamTypes/Index')
                ->has('examTypes', 2)
                ->where('can.manage', false));

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/exam-types')->assertForbidden();
    }

    public function test_super_admin_can_create_update_and_delete(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/exam-types', [
            'name' => 'Career Service Examination',
            'is_active' => true,
        ])->assertRedirect();

        $type = ExamType::firstOrFail();

        $this->actingAs($admin)->put("/exam-types/{$type->id}", [
            'name' => 'CSE-PPT',
            'is_active' => false,
        ])->assertRedirect();
        $this->assertSame('CSE-PPT', $type->fresh()->name);

        $this->actingAs($admin)->delete("/exam-types/{$type->id}")->assertRedirect();
        $this->assertModelMissing($type);
    }

    public function test_fo_admin_cannot_create_exam_type(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($foAdmin)->post('/exam-types', [
            'name' => 'Career Service Examination',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        ExamType::factory()->create(['name' => 'Existing Type']);

        $this->actingAs($admin)->post('/exam-types', [
            'name' => 'Existing Type',
            'is_active' => true,
        ])->assertSessionHasErrors('name');
    }

    public function test_type_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $type = ExamType::factory()->create();
        Examination::factory()->create(['exam_type_id' => $type->id]);

        $this->actingAs($admin)->delete("/exam-types/{$type->id}")->assertStatus(422);
        $this->assertModelExists($type);
    }
}
