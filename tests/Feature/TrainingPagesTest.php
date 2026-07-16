<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainings_index_renders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        Training::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/trainings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Trainings/Index')
                ->has('trainings', 2));
    }

    public function test_training_modal_returns_json(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $training = Training::factory()->create();
        TrainingAssignment::factory()->create(['training_id' => $training->id, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->getJson("/trainings/{$training->id}/modal")
            ->assertOk()
            ->assertJson([
                'training' => ['id' => $training->id],
                'can' => ['assign' => true, 'complete' => true, 'manage' => true],
            ])
            ->assertJsonCount(1, 'assignments');
    }

    public function test_member_cannot_view_trainings_index(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/trainings')->assertForbidden();
    }

    public function test_completing_training_issues_certificates_and_updates_modal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $training = Training::factory()->create();
        $assignment = TrainingAssignment::factory()->create([
            'training_id' => $training->id,
            'attendance_confirmed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/trainings/{$training->id}/complete")
            ->assertRedirect();

        $this->assertNotNull($training->fresh()->completed_at);

        $this->actingAs($admin)
            ->getJson("/trainings/{$training->id}/modal")
            ->assertOk()
            ->assertJson(['training' => ['completed' => true]]);
    }

    public function test_fo_admin_can_create_training(): void
    {
        $office = FieldOffice::create(['name' => 'Cebu Field Office', 'code' => 'CEB']);
        $exam = Examination::factory()->create();
        $foAdmin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $office->id,
        ]);

        $this->actingAs($foAdmin)
            ->post('/trainings', [
                'title' => 'Cebu Orientation',
                'type' => 'briefing',
                'training_date' => '2026-08-01',
                'exam_id' => $exam->id,
            ])
            ->assertRedirect();

        $training = Training::where('title', 'Cebu Orientation')->first();
        $this->assertNotNull($training);
        $this->assertEquals($office->id, $training->field_office_id);
        $this->assertEquals($exam->id, $training->exam_id);
    }

    public function test_fo_admin_only_sees_their_office_trainings_in_index(): void
    {
        $office1 = FieldOffice::create(['name' => 'Office A', 'code' => 'OFFA']);
        $office2 = FieldOffice::create(['name' => 'Office B', 'code' => 'OFFB']);

        Training::factory()->create([
            'title' => 'Office A Training',
            'field_office_id' => $office1->id,
        ]);
        Training::factory()->create([
            'title' => 'Office B Training',
            'field_office_id' => $office2->id,
        ]);

        $foAdmin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $office1->id,
        ]);

        $this->actingAs($foAdmin)
            ->get('/trainings')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Trainings/Index')
                ->has('trainings', 1)
                ->where('trainings.0.title', 'Office A Training'));
    }

    public function test_super_admin_can_manage_all_trainings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $exam = Examination::factory()->create();
        $training = Training::factory()->create(['exam_id' => $exam->id]);

        $this->actingAs($admin)
            ->get('/trainings')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Trainings/Index')
                ->where('can.manage', true));

        $this->actingAs($admin)
            ->put("/trainings/{$training->id}", [
                'title' => 'Updated Title',
                'type' => 'briefing',
                'training_date' => '2026-09-01',
                'exam_id' => $exam->id,
            ])
            ->assertRedirect();

        $this->assertEquals('Updated Title', $training->fresh()->title);
    }

    public function test_fo_admin_cannot_delete_training(): void
    {
        $office = FieldOffice::create(['name' => 'Office', 'code' => 'OFF']);
        $training = Training::factory()->create(['field_office_id' => $office->id]);
        $foAdmin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $office->id,
        ]);

        $this->actingAs($foAdmin)
            ->delete("/trainings/{$training->id}")
            ->assertForbidden();
    }
}
