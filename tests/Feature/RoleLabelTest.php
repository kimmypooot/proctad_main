<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\RoleLabelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RoleLabelRegistry::flush();
    }

    public function test_a_role_falls_back_to_its_built_in_label(): void
    {
        $this->assertSame('Field Office Staff', UserRole::FoAdmin->label());
        $this->assertSame(UserRole::FoAdmin->defaultLabel(), UserRole::FoAdmin->label());
    }

    public function test_super_admin_can_rename_a_role(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->put('/roles', [
            'role' => UserRole::FoAdmin->value,
            'label' => 'Field Office Personnel',
        ])->assertRedirect()->assertSessionHasNoErrors();

        RoleLabelRegistry::flush();

        $this->assertSame('Field Office Personnel', UserRole::FoAdmin->label());
        // The built-in name is still available to restore from.
        $this->assertSame('Field Office Staff', UserRole::FoAdmin->defaultLabel());
    }

    /**
     * The whole safety claim of this page: a rename is presentation only. The
     * stored role value and every permission attached to it are untouched.
     */
    public function test_renaming_does_not_change_what_the_role_can_do(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $before = collect(Permission::cases())
            ->mapWithKeys(fn (Permission $p) => [$p->value => $foAdmin->hasPermission($p)]);

        $this->actingAs($superAdmin)->put('/roles', [
            'role' => UserRole::FoAdmin->value,
            'label' => 'Something Else Entirely',
        ])->assertRedirect();

        $foAdmin = $foAdmin->fresh();

        $this->assertSame(UserRole::FoAdmin, $foAdmin->role);

        foreach (Permission::cases() as $permission) {
            $this->assertSame(
                $before[$permission->value],
                $foAdmin->hasPermission($permission),
                "Renaming changed {$permission->value}",
            );
        }
    }

    /** Renaming is the only way a label changes, including changing it back. */
    public function test_a_role_can_be_renamed_more_than_once(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        RoleLabelRegistry::set(UserRole::FoAdmin, 'Temporary Name');
        $this->assertSame('Temporary Name', UserRole::FoAdmin->label());

        $this->actingAs($superAdmin)->put('/roles', [
            'role' => UserRole::FoAdmin->value,
            'label' => 'Field Office Personnel',
        ])->assertRedirect();

        RoleLabelRegistry::flush();

        // One row per role, updated in place rather than accumulating.
        $this->assertDatabaseCount('role_labels', 1);
        $this->assertSame('Field Office Personnel', UserRole::FoAdmin->label());
    }

    public function test_only_super_admin_reaches_the_page(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($superAdmin)->get('/roles')->assertOk();

        // ESD Admin manages users and permissions but not the roles themselves.
        $this->actingAs($esdAdmin)->get('/roles')->assertForbidden();
        $this->actingAs($foAdmin)->get('/roles')->assertForbidden();
    }

    public function test_the_super_admin_gate_cannot_be_granted_away(): void
    {
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        // Even holding every permission, ESD Admin is still refused: the page is
        // gated on the role itself, not on anything the matrix can toggle.
        $this->actingAs($esdAdmin)->put('/roles', [
            'role' => UserRole::FoAdmin->value,
            'label' => 'Hijacked',
        ])->assertForbidden();

        $this->assertSame('Field Office Staff', UserRole::FoAdmin->label());
    }

    /**
     * A rename has to show up wherever a role is named, not just on the Roles
     * page — every label resolves through UserRole::label(), and the sidebar
     * reads the shared roleLabels prop rather than its own copy.
     */
    public function test_a_rename_propagates_to_other_pages(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        RoleLabelRegistry::set(UserRole::DirectorIv, 'Director IV');

        $this->actingAs($superAdmin)->get('/users')->assertInertia(
            fn (Assert $page) => $page
                ->where('roleLabels.director_iv', 'Director IV')
                ->where(
                    'roles',
                    fn ($roles) => collect($roles)
                        ->firstWhere('value', UserRole::DirectorIv->value)['label'] === 'Director IV',
                ),
        );

        $this->actingAs($superAdmin)->get('/role-permissions')->assertInertia(
            fn (Assert $page) => $page->where(
                'roles',
                fn ($roles) => collect($roles)
                    ->firstWhere('value', UserRole::DirectorIv->value)['label'] === 'Director IV',
            ),
        );
    }

    public function test_a_rename_requires_a_label(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->put('/roles', [
            'role' => UserRole::FoAdmin->value,
            'label' => '',
        ])->assertSessionHasErrors('label');
    }
}
