<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Member;
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

    /**
     * Blank means "no office", never "region-wide" — and a Field Office role
     * with no office derives no jurisdiction at all, so it is rejected.
     */
    public function test_field_office_is_required_for_field_office_roles_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@csc.gov.ph',
            'role' => 'fo_admin',
        ])->assertSessionHasErrors('field_office_id');

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@csc.gov.ph',
            'role' => 'director_iv',
        ])->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'maria@csc.gov.ph')->value('field_office_id'));
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
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'fo_admin',
            'field_office_id' => $fo->id,
            'is_active' => false,
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame(UserRole::FoAdmin, $target->role);
        $this->assertSame($fo->id, $target->field_office_id);
        $this->assertFalse($target->is_active);
    }

    public function test_admin_can_correct_a_users_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => FieldOffice::factory()->create()->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'name' => 'Maria Santos',
        ]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Cruz',
            'suffix' => 'Jr.',
            'role' => $target->role->value,
            'field_office_id' => $target->field_office_id,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Cruz', $target->last_name);
        $this->assertSame('Reyes', $target->middle_name);
        // The display name is rebuilt from the parts rather than left stale.
        $this->assertSame('Maria Reyes Cruz Jr.', $target->name);
    }

    public function test_updating_a_user_requires_a_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'first_name' => '',
            'last_name' => '',
            'role' => 'member',
            'is_active' => true,
        ])->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_admin_cannot_change_a_users_email_or_username(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'email' => 'original@csc.gov.ph',
            'username' => 'original',
        ]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'hijacked@example.com',
            'username' => 'hijacked',
            'role' => $target->role->value,
            'field_office_id' => $target->field_office_id,
            'is_active' => true,
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('original@csc.gov.ph', $target->email);
        $this->assertSame('original', $target->username);
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
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'role' => 'fo_admin',
            'field_office_id' => $samar->id,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame([$samarCenter->id], $target->testingCenters()->pluck('testing_centers.id')->all());

        // Promoting to a regional role with no field office clears the links.
        $this->actingAs($admin)->put("/users/{$target->id}", [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
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
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
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

    /**
     * A staff account with no accreditation and a member account are each on
     * exactly one tab: the member must not appear among the staff or the Field
     * Office column would read "—" for every one of them, which is what the
     * split exists to fix.
     */
    public function test_tabs_separate_staff_accounts_from_test_administrators(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'name' => 'Zoe Admin']);
        $member = User::factory()->create(['role' => UserRole::Member, 'name' => 'Ana Administrator']);

        $this->actingAs($admin)
            ->get('/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'staff')
                ->where('counts.staff', 1)
                ->where('counts.members', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $admin->id));

        $this->actingAs($admin)
            ->get('/users?tab=members')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'members')
                ->has('users.data', 1)
                ->where('users.data.0.id', $member->id));
    }

    /**
     * Commission staff can hold an accreditation as well — that is what the
     * workspace switcher is for (App\Support\Workspace) — so a Field Office
     * Staff who proctors is administered under both hats and must be reachable
     * on both tabs, carrying their registry details on either one.
     */
    public function test_staff_who_hold_an_accreditation_appear_on_both_tabs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $center = TestingCenter::factory()->create(['name' => 'Tacloban City']);
        $dualHat = User::factory()->create(['role' => UserRole::FoAdmin, 'name' => 'Rosa Proctor']);
        Member::factory()->create([
            'user_id' => $dualHat->id,
            'email' => $dualHat->email,
            'testing_center_id' => $center->id,
        ]);

        $this->actingAs($admin)
            ->get('/users?tab=members')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.members', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $dualHat->id)
                ->where('users.data.0.member.testing_center.name', 'Tacloban City'));

        $this->actingAs($admin)
            ->get('/users?search=Rosa')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'staff')
                ->has('users.data', 1)
                ->where('users.data.0.id', $dualHat->id)
                // The staff tab carries the accreditation too, so the row can
                // say so and link through to the registry record.
                ->where('users.data.0.member.testing_center.name', 'Tacloban City'));
    }

    /**
     * Regression: the filter compared `$request->string('linked')` — a
     * Stringable object — with `===` against a string, which is never true in
     * PHP. The condition was always false, so clicking "Awaiting PROCTAD
     * Registration" reloaded the page with the full list and no explanation.
     */
    public function test_awaiting_registration_filter_returns_only_unlinked_member_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $unlinked = User::factory()->create(['role' => UserRole::Member]);
        $linked = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $linked->id, 'email' => $linked->email]);

        $this->actingAs($admin)
            ->get('/users?tab=members&linked=unlinked')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.unlinked', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $unlinked->id)
                ->where('users.data.0.has_member_record', false));
    }

    /** The members tab is scoped by testing center, which staff accounts do not have. */
    public function test_test_administrators_can_be_filtered_by_testing_center(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $tacloban = TestingCenter::factory()->create(['name' => 'Tacloban City']);
        $ormoc = TestingCenter::factory()->create(['name' => 'Ormoc City']);

        $here = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $here->id, 'testing_center_id' => $tacloban->id]);
        $elsewhere = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $elsewhere->id, 'testing_center_id' => $ormoc->id]);

        $this->actingAs($admin)
            ->get("/users?tab=members&testing_center_id={$tacloban->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $here->id)
                ->where('users.data.0.member.testing_center.name', 'Tacloban City'));
    }

    /**
     * Creating a member account here would produce a login with no registry
     * record — the exact state the "no linked PROCTAD record" queue exists to
     * drain. Test administrators self-register, which creates both at once.
     */
    public function test_member_accounts_cannot_be_created_from_the_users_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'role' => 'member',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'juan@example.com']);
    }
}
