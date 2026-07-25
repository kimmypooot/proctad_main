<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_update_writes_audit_entry_with_actor_and_diff(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'agency' => 'Old Agency']);

        $this->actingAs($admin);
        $member->update(['agency' => 'New Agency']);

        $log = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($office->id, $log->field_office_id);
        $this->assertSame('Old Agency', $log->changes['old']['agency']);
        $this->assertSame('New Agency', $log->changes['new']['agency']);

        // Creation was audited too.
        $this->assertTrue(AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('action', 'created')
            ->exists());
    }

    public function test_password_is_never_recorded_in_audit_changes(): void
    {
        $user = User::factory()->create();

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->changes['new']);
        $this->assertArrayNotHasKey('remember_token', $log->changes['new']);
    }

    public function test_field_director_sees_only_own_field_office_entries(): void
    {
        $leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);

        Member::factory()->create(['field_office_id' => $leyte->id]);
        Member::factory()->create(['field_office_id' => $samar->id]);

        $director = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $leyte->id]);
        $leyteMemberLogs = AuditLog::where('field_office_id', $leyte->id)->count();

        $this->actingAs($director)
            ->get('/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AuditLogs/Index')
                ->has('logs.data', $leyteMemberLogs));
    }

    public function test_fo_admin_sees_only_own_field_office_entries(): void
    {
        $leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);

        Member::factory()->create(['field_office_id' => $leyte->id]);
        Member::factory()->create(['field_office_id' => $samar->id]);

        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte->id]);
        $leyteMemberLogs = AuditLog::where('field_office_id', $leyte->id)->count();

        $this->actingAs($admin)
            ->get('/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AuditLogs/Index')
                ->has('logs.data', $leyteMemberLogs));
    }

    public function test_access_is_limited_to_authorized_roles(): void
    {
        // Field-office staff (Field Director and FO Admin) share the same access;
        // both may open the Audit Trail, scoped to their own office.
        foreach ([UserRole::SuperAdmin, UserRole::DirectorIv, UserRole::DirectorIii, UserRole::FieldDirector, UserRole::FoAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/audit-logs')
                ->assertOk();
        }

        foreach ([UserRole::EsdAdmin, UserRole::Member] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/audit-logs')
                ->assertForbidden();
        }
    }
}
