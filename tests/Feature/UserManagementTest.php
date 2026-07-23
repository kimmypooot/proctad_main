<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_restricted_to_super_admin_and_esd_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings/Users/Index'));

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $this->actingAs($foAdmin)->get('/users')->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_reset_link_is_sent(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@csc.gov.ph',
            'username' => 'jdelacruz',
            'role' => 'fo_admin',
            'field_office_id' => $fo->id,
        ])->assertRedirect();

        $user = User::where('email', 'juan@csc.gov.ph')->firstOrFail();
        $this->assertSame(UserRole::FoAdmin, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->is_active);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $existing = User::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $existing->email,
            'role' => 'fo_admin',
        ])->assertSessionHasErrors('email');
    }

    public function test_fo_admin_cannot_create_users(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($foAdmin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@csc.gov.ph',
            'role' => 'fo_admin',
        ])->assertForbidden();
    }

    public function test_admin_can_update_role_field_office_and_deactivate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $target = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'role' => 'fo_admin',
            'field_office_id' => $fo->id,
            'is_active' => false,
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame(UserRole::FoAdmin, $target->role);
        $this->assertSame($fo->id, $target->field_office_id);
        $this->assertFalse($target->is_active);
    }

    public function test_creating_a_user_links_them_to_their_field_offices_testing_centers(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $centerA = TestingCenter::factory()->forFieldOffice($fo)->create();
        $centerB = TestingCenter::factory()->forFieldOffice($fo)->create();

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@csc.gov.ph',
            'role' => 'fo_admin',
            'field_office_id' => $fo->id,
        ])->assertRedirect();

        $user = User::where('email', 'juan@csc.gov.ph')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$centerA->id, $centerB->id],
            $user->testingCenters()->pluck('testing_centers.id')->all(),
        );
    }

    public function test_updating_a_users_field_office_resyncs_their_testing_centers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $leyte = FieldOffice::factory()->create();
        $samar = FieldOffice::factory()->create();
        $leyteCenter = TestingCenter::factory()->forFieldOffice($leyte)->create();
        $samarCenter = TestingCenter::factory()->forFieldOffice($samar)->create();

        $target = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte->id]);
        $target->testingCenters()->sync([$leyteCenter->id]);

        // Moving the user to Samar drags their center links along with the office.
        $this->actingAs($admin)->put("/users/{$target->id}", [
            'role' => 'fo_admin',
            'field_office_id' => $samar->id,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame([$samarCenter->id], $target->testingCenters()->pluck('testing_centers.id')->all());

        // Promoting to a regional role with no field office clears the links.
        $this->actingAs($admin)->put("/users/{$target->id}", [
            'role' => 'esd_admin',
            'field_office_id' => null,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertCount(0, $target->testingCenters()->get());
    }

    public function test_resync_command_catches_users_up_to_a_newly_added_center(): void
    {
        $fo = FieldOffice::factory()->create();
        $centerA = TestingCenter::factory()->forFieldOffice($fo)->create();
        $user = User::factory()->create(['field_office_id' => $fo->id]);

        // On save the observer linked only the center that existed then.
        $this->assertSame([$centerA->id], $user->testingCenters()->pluck('testing_centers.id')->all());

        // A new center is later added to the office — existing users don't gain
        // it automatically, since nothing re-saved them.
        $centerB = TestingCenter::factory()->forFieldOffice($fo)->create();
        $this->assertSame([$centerA->id], $user->fresh()->testingCenters()->pluck('testing_centers.id')->all());

        $this->artisan('proctad:resync-user-testing-centers')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$centerA->id, $centerB->id],
            $user->fresh()->testingCenters()->pluck('testing_centers.id')->all(),
        );
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'role' => 'super_admin',
            'is_active' => false,
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_admin_can_send_password_reset_and_it_is_audited(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post("/users/{$target->id}/send-password-reset")
            ->assertRedirect();

        Notification::assertSentTo($target, ResetPassword::class);
        $this->assertTrue(
            AuditLog::where('user_id', $target->id)->where('action', 'password_reset_sent')->exists(),
        );
    }
}
