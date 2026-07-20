<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, ?FieldOffice $office = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'field_office_id' => $office?->id,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $this->actingAs($this->makeUser(UserRole::Member))
            ->get('/login')
            ->assertRedirect('/dashboard');
    }

    public function test_login_redirects_to_dashboard(): void
    {
        $user = $this->makeUser(UserRole::Member);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_each_role_sees_its_own_dashboard(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);

        foreach (UserRole::cases() as $role) {
            $user = $this->makeUser($role, $role->isRegionWide() ? null : $office);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dashboard/Index')
                    ->where('role', $role->value)
                    ->where('roleLabel', $role->label())
                    ->has('stats', 4)
                    ->where('fieldOffice', $role->isRegionWide()
                        ? null
                        : ['id' => $office->id, 'name' => $office->name, 'code' => $office->code]));
        }
    }

    public function test_role_middleware_blocks_unauthorized_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:esd_admin,super_admin'])
            ->get('/_test/admin-only', fn () => 'ok');

        $this->actingAs($this->makeUser(UserRole::Member))
            ->get('/_test/admin-only')
            ->assertForbidden();

        $this->actingAs($this->makeUser(UserRole::EsdAdmin))
            ->get('/_test/admin-only')
            ->assertOk();
    }
}
