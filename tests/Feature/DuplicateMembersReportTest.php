<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DuplicateMembersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_view_the_report(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get('/reports/duplicate-members')
            ->assertOk();

        foreach ([UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector, UserRole::DirectorIv, UserRole::DirectorIii] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/reports/duplicate-members')
                ->assertForbidden();
        }
    }

    public function test_flags_members_with_duplicate_normalized_names(): void
    {
        $fo = FieldOffice::factory()->create();
        Member::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'field_office_id' => $fo->id]);
        Member::factory()->create(['first_name' => ' juan ', 'last_name' => ' DELA CRUZ ', 'field_office_id' => $fo->id]);
        Member::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'field_office_id' => $fo->id]);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports/duplicate-members')->assertInertia(fn (Assert $page) => $page
            ->component('Reports/DuplicateMembers')
            ->has('nameGroups', 1)
            ->has('nameGroups.0.members', 2)
            ->has('emailGroups', 0)
        );
    }

    public function test_no_false_positives_for_unique_members(): void
    {
        Member::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Member::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get('/reports/duplicate-members')->assertInertia(fn (Assert $page) => $page
            ->has('nameGroups', 0)
            ->has('emailGroups', 0)
        );
    }
}
