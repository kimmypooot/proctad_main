<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\UserRole;
use App\Models\FeeSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_and_esd_admin_can_view_fee_schedules(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::EsdAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/fee-schedules')
                ->assertOk();
        }

        foreach ([UserRole::Management, UserRole::FieldDirector, UserRole::FoAdmin, UserRole::Member] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/fee-schedules')
                ->assertForbidden();
        }
    }

    public function test_updating_a_fee_schedule_creates_or_updates_the_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->put('/fee-schedules', [
            'payee_type' => PayeeType::ExamRole->value,
            'payee_value' => ExamRole::Proctor->value,
            'amount' => 1400,
        ])->assertRedirect();

        $this->assertDatabaseHas('fee_schedules', [
            'payee_type' => PayeeType::ExamRole->value,
            'payee_value' => ExamRole::Proctor->value,
            'amount_cents' => 140000,
        ]);

        $this->actingAs($admin)->put('/fee-schedules', [
            'payee_type' => PayeeType::ExamRole->value,
            'payee_value' => ExamRole::Proctor->value,
            'amount' => 1500,
        ])->assertRedirect();

        $this->assertEquals(1, FeeSchedule::where('payee_value', ExamRole::Proctor->value)->count());
        $this->assertDatabaseHas('fee_schedules', [
            'payee_value' => ExamRole::Proctor->value,
            'amount_cents' => 150000,
        ]);
    }

    public function test_invalid_payee_value_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->put('/fee-schedules', [
            'payee_type' => PayeeType::ExamRole->value,
            'payee_value' => 'not_a_real_role',
            'amount' => 1400,
        ])->assertStatus(422);
    }
}
