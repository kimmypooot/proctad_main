<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D1: single guard, but members must never reach staff functionality.
 */
class GuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => UserRole::Member]);
    }

    public function test_member_cannot_access_staff_routes(): void
    {
        $member = $this->member();

        foreach (['/members', '/examinations', '/trainings', '/certificates', '/signatories', '/scanner'] as $uri) {
            $this->actingAs($member)->get($uri)->assertForbidden();
        }
    }

    public function test_member_cannot_access_approval_routes(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get('/audit-logs')->assertForbidden();
        $this->actingAs($member)->get('/approvals')->assertForbidden();
    }

    public function test_member_can_access_self_service_routes(): void
    {
        $member = $this->member();

        foreach (['/dashboard', '/my/profile', '/my/qr-code', '/my/service-history'] as $uri) {
            $this->actingAs($member)->get($uri)->assertOk();
        }
    }

    public function test_field_office_staff_cannot_access_certificate_approvals(): void
    {
        // FO Admin and Field Director share the same access; approving
        // certificates is the one thing reserved for the Field Director.
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($foAdmin)->get('/approvals')->assertForbidden();

        // The Audit Trail is shared, scoped to the staff member's own office.
        $this->actingAs($foAdmin)->get('/audit-logs')->assertOk();
    }
}
