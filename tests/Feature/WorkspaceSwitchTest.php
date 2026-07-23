<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Staff who are also accredited PROCTAD members switch between their staff
 * console and their own member pages on one account. The switch is a view
 * toggle: it must never widen or narrow what they may actually do.
 */
class WorkspaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function accreditedStaff(UserRole $role = UserRole::FoAdmin): User
    {
        $user = User::factory()->create(['role' => $role]);
        Member::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_accredited_staff_are_offered_the_switch(): void
    {
        $this->actingAs($this->accreditedStaff())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canSwitchWorkspace', true)
                ->where('workspace', Workspace::STAFF)
                // Default is the staff console, not the member view.
                ->where('role', UserRole::FoAdmin->value));
    }

    public function test_staff_without_an_accreditation_are_not_offered_the_switch(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::FoAdmin]))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('canSwitchWorkspace', false));
    }

    public function test_switching_shows_the_member_dashboard(): void
    {
        $user = $this->accreditedStaff();

        $this->actingAs($user)
            ->post('/workspace', ['workspace' => Workspace::MEMBER])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace', Workspace::MEMBER)
                ->where('role', UserRole::Member->value)
                ->has('memberSummary'));
    }

    public function test_switching_back_restores_the_staff_console(): void
    {
        $user = $this->accreditedStaff();

        $this->actingAs($user)->post('/workspace', ['workspace' => Workspace::MEMBER]);
        $this->actingAs($user)->post('/workspace', ['workspace' => Workspace::STAFF]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace', Workspace::STAFF)
                ->where('role', UserRole::FoAdmin->value));
    }

    public function test_staff_without_an_accreditation_cannot_switch(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::FoAdmin]))
            ->post('/workspace', ['workspace' => Workspace::MEMBER])
            ->assertForbidden();
    }

    public function test_an_unknown_workspace_is_rejected(): void
    {
        $this->actingAs($this->accreditedStaff())
            ->post('/workspace', ['workspace' => 'admin'])
            ->assertSessionHasErrors('workspace');
    }

    /**
     * The whole point of the design: the toggle is presentation. A staff user
     * sitting in the member workspace keeps every staff power they had, and a
     * plain member gains none by forging the session value.
     */
    public function test_the_workspace_does_not_change_authorization(): void
    {
        $user = $this->accreditedStaff(UserRole::SuperAdmin);

        $this->actingAs($user)->post('/workspace', ['workspace' => Workspace::MEMBER]);

        $this->actingAs($user)->get('/members')->assertOk();
    }

    public function test_a_plain_member_cannot_forge_a_staff_workspace(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/workspace', ['workspace' => Workspace::STAFF])
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace', Workspace::MEMBER)
                ->where('canSwitchWorkspace', false));
    }

    /**
     * The session outlives the record. An accreditation removed while the user
     * was switched in must drop them back to the console rather than stranding
     * them on a member dashboard with nothing behind it.
     */
    public function test_losing_the_accreditation_falls_back_to_staff(): void
    {
        $user = $this->accreditedStaff();

        $this->actingAs($user)->post('/workspace', ['workspace' => Workspace::MEMBER]);

        $user->member()->delete();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace', Workspace::STAFF)
                ->where('role', UserRole::FoAdmin->value));
    }
}
