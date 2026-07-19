<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function close(): void
    {
        Setting::set('site_maintenance_mode', true, 'boolean');
    }

    public function test_public_pages_are_served_normally_when_it_is_off(): void
    {
        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
    }

    public function test_public_pages_return_the_notice_when_it_is_on(): void
    {
        $this->close();

        // 503, not 200 or a redirect: search engines must treat this as
        // temporary rather than indexing the notice as the homepage.
        $this->get('/')
            ->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page->component('Maintenance'));

        $this->get('/about')->assertStatus(503);
        $this->get('/faqs')->assertStatus(503);
    }

    public function test_signed_in_staff_keep_working_while_it_is_on(): void
    {
        $this->close();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();
        // Including the public site, so they can check what visitors would see.
        $this->actingAs($admin)->get('/')->assertOk();
    }

    /** Every Commission role works through maintenance, not just Super Admin. */
    public function test_all_staff_roles_bypass_maintenance(): void
    {
        $this->close();

        $roles = [
            UserRole::SuperAdmin,
            UserRole::EsdAdmin,
            UserRole::Management,
            UserRole::FieldDirector,
            UserRole::FoAdmin,
        ];

        foreach ($roles as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)
                ->get('/')
                ->assertOk();
        }
    }

    /**
     * Members are treated as the public: they may sign in, but the portal shows
     * the notice once they are through.
     */
    public function test_members_are_shown_the_notice_even_when_signed_in(): void
    {
        $this->close();

        $member = User::factory()->create(['role' => UserRole::Member]);

        // They can still reach and use sign-in.
        $this->get('/login')->assertOk();

        $this->actingAs($member)->get('/')->assertStatus(503);
        $this->actingAs($member)->get('/my/profile')->assertStatus(503);
    }

    public function test_sign_in_stays_reachable_so_staff_can_switch_it_back_off(): void
    {
        $this->close();

        $this->get('/login')->assertOk();
        $this->get('/member/login')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    /**
     * These are operational, not promotional — closing them would strand people
     * mid-task on exam day with no way through.
     */
    public function test_verification_stays_open_for_venue_use(): void
    {
        $this->close();

        $member = Member::factory()->create();

        $this->get("/verify/{$member->proctad_id}")->assertOk();
        $this->get('/verify-certificate/RO8-CAP-2026-99999')->assertOk();
    }

    public function test_emailed_assignment_confirmation_links_still_work(): void
    {
        $this->close();

        $assignment = ExamAssignment::factory()->create();
        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->get($url)->assertOk();
    }

    public function test_post_examination_evaluation_stays_open(): void
    {
        $this->close();

        $this->get('/evaluation')->assertOk();
    }

    public function test_staff_see_a_banner_so_it_is_not_left_on_by_accident(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('maintenanceMode', false));

        $this->close();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('maintenanceMode', true));
    }

    /**
     * Staff pass through maintenance mode, so on the public site they see a
     * working page and reasonably conclude the switch is broken. The flag has
     * to reach public pages too, so the layout can explain why.
     */
    public function test_staff_viewing_the_public_site_are_told_why_they_can_see_it(): void
    {
        $this->close();

        // Guest first: actingAs persists for the rest of the test.
        $this->get('/')->assertStatus(503);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('maintenanceMode', true));
    }
}
