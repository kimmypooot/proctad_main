<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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

    public function test_training_show_renders_with_participants(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $training = Training::factory()->create();
        TrainingAssignment::factory()->create(['training_id' => $training->id, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/trainings/{$training->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Trainings/Show')
                ->where('training.id', $training->id)
                ->has('assignments', 1));
    }

    public function test_member_cannot_view_trainings_index(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/trainings')->assertForbidden();
    }

    public function test_completing_training_issues_certificates_and_renders_back(): void
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
            ->get("/trainings/{$training->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('training.completed', true));
    }
}
