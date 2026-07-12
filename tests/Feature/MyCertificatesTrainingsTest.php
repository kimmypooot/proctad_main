<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyCertificatesTrainingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_member_sees_own_certificates(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);
        $assignment = ExamAssignment::factory()->create(['member_id' => $member->id]);

        Certificate::create([
            'type' => 'appearance',
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => 'released',
            'certificate_no' => 'RO8-COA-2026-00001',
        ]);

        $this->actingAs($user)
            ->get('/my/certificates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/Certificates')
                ->where('hasRecord', true)
                ->has('certificates', 1)
                ->where('certificates.0.certificate_no', 'RO8-COA-2026-00001'));
    }

    public function test_unlinked_user_gets_empty_state_for_certificates(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($user)
            ->get('/my/certificates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hasRecord', false));
    }

    public function test_linked_member_sees_own_trainings(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);
        $training = Training::factory()->create(['title' => 'PROCTAD Orientation']);
        TrainingAssignment::factory()->create(['member_id' => $member->id, 'training_id' => $training->id]);

        $this->actingAs($user)
            ->get('/my/trainings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/Trainings')
                ->where('hasRecord', true)
                ->has('records', 1)
                ->where('records.0.title', 'PROCTAD Orientation'));
    }

    public function test_unlinked_user_gets_empty_state_for_trainings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($user)
            ->get('/my/trainings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hasRecord', false));
    }
}
