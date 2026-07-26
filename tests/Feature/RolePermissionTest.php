<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PermissionRegistry::flush();
    }

    private function grant(UserRole $role, Permission $permission, bool $granted): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role' => $role->value, 'permission' => $permission->value],
            ['granted' => $granted, 'created_at' => now(), 'updated_at' => now()],
        );

        PermissionRegistry::flush();
    }

    public function test_defaults_reproduce_the_original_role_tiers(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        // Field Office staff could always register members but never create an
        // examination; members hold nothing at all.
        $this->assertTrue($foAdmin->hasPermission(Permission::MembersManage));
        $this->assertFalse($foAdmin->hasPermission(Permission::ExaminationsManage));
        $this->assertTrue($esdAdmin->hasPermission(Permission::ExaminationsManage));
        $this->assertFalse($member->hasPermission(Permission::MembersView));
    }

    public function test_super_admin_holds_every_permission_even_if_the_table_says_otherwise(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->grant(UserRole::SuperAdmin, Permission::UsersManage, false);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermission($permission),
                "Super Admin should hold {$permission->value}",
            );
        }
    }

    public function test_revoking_a_permission_denies_the_action(): void
    {
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($esdAdmin)->get('/examinations')->assertOk();

        $this->grant(UserRole::EsdAdmin, Permission::ExaminationsView, false);

        $this->actingAs($esdAdmin)->get('/examinations')->assertForbidden();
    }

    public function test_granting_a_permission_allows_a_role_that_previously_lacked_it(): void
    {
        $foAdmin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => FieldOffice::factory()->create()->id,
        ]);

        $this->assertFalse($foAdmin->can('create', \App\Models\Examination::class));

        $this->grant(UserRole::FoAdmin, Permission::ExaminationsManage, true);

        $this->assertTrue($foAdmin->fresh()->can('create', \App\Models\Examination::class));
    }

    /**
     * The property the whole design rests on: a permission widens who may act,
     * never which records they may reach. A Field Office role handed a
     * permission still cannot touch another office's testing center.
     */
    public function test_granting_a_permission_does_not_widen_jurisdiction(): void
    {
        $leyte = FieldOffice::factory()->create();
        $samar = FieldOffice::factory()->create();
        $leyteCenter = TestingCenter::factory()->forFieldOffice($leyte)->create();
        $samarCenter = TestingCenter::factory()->forFieldOffice($samar)->create();

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte->id]);
        $ownMember = Member::factory()->create(['testing_center_id' => $leyteCenter->id]);
        $otherMember = Member::factory()->create(['testing_center_id' => $samarCenter->id]);

        // Every members permission granted, including ones the role never had.
        foreach ([Permission::MembersView, Permission::MembersManage] as $permission) {
            $this->grant(UserRole::FoAdmin, $permission, true);
        }

        $foAdmin = $foAdmin->fresh();

        $this->assertTrue($foAdmin->can('update', $ownMember));
        $this->assertFalse($foAdmin->can('update', $otherMember));
    }

    public function test_only_a_user_manager_can_open_the_matrix(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($foAdmin)->get('/role-permissions')->assertForbidden();
        $this->actingAs($superAdmin)->get('/role-permissions')->assertOk();
    }

    public function test_super_admin_permissions_cannot_be_edited(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->put('/role-permissions', [
            'role' => UserRole::SuperAdmin->value,
            'permission' => Permission::UsersManage->value,
            'granted' => false,
        ])->assertStatus(422);

        $this->assertDatabaseCount('role_permissions', 0);
    }

    public function test_toggling_through_the_endpoint_persists_and_takes_effect(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($superAdmin)->put('/role-permissions', [
            'role' => UserRole::FoAdmin->value,
            'permission' => Permission::MembersManage->value,
            'granted' => false,
        ])->assertRedirect();

        $this->assertFalse($foAdmin->fresh()->hasPermission(Permission::MembersManage));
    }

    public function test_resetting_a_role_restores_the_defaults(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->grant(UserRole::FoAdmin, Permission::MembersManage, false);
        $this->assertFalse($foAdmin->fresh()->hasPermission(Permission::MembersManage));

        $this->actingAs($superAdmin)->delete('/role-permissions', [
            'role' => UserRole::FoAdmin->value,
        ])->assertRedirect();

        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertTrue($foAdmin->fresh()->hasPermission(Permission::MembersManage));
    }
}
