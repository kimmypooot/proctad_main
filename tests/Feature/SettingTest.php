<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_and_esd_admin_can_view_settings(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::EsdAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/settings')
                ->assertOk();
        }

        foreach ([UserRole::FoAdmin, UserRole::FieldDirector, UserRole::Management] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/settings')
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_create_a_setting(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/settings', [
            'key' => 'reminder_days_before_exam',
            'value' => '3',
            'type' => 'number',
            'description' => 'Days before exam to send reminders',
            'is_public' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'reminder_days_before_exam',
            'value' => '3',
            'type' => 'number',
        ]);
    }

    public function test_setting_key_must_be_unique_and_well_formed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        Setting::create(['key' => 'existing_key', 'value' => 'x', 'type' => 'string']);

        $this->actingAs($admin)->post('/settings', [
            'key' => 'existing_key',
            'type' => 'string',
            'is_public' => false,
        ])->assertSessionHasErrors('key');

        $this->actingAs($admin)->post('/settings', [
            'key' => 'Not A Valid Key!',
            'type' => 'string',
            'is_public' => false,
        ])->assertSessionHasErrors('key');
    }

    public function test_esd_admin_can_update_a_setting_value(): void
    {
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $setting = Setting::create(['key' => 'foo', 'value' => 'old', 'type' => 'string']);

        $this->actingAs($admin)->put("/settings/{$setting->id}", [
            'value' => 'new',
            'description' => 'Updated',
            'is_public' => true,
        ])->assertRedirect();

        $setting->refresh();
        $this->assertSame('new', $setting->value);
        $this->assertTrue($setting->is_public);
    }

    public function test_updating_a_setting_invalidates_its_cached_value(): void
    {
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $setting = Setting::create(['key' => 'cached_key', 'value' => 'v1', 'type' => 'string']);

        $this->assertSame('v1', Setting::get('cached_key'));

        $this->actingAs($admin)->put("/settings/{$setting->id}", [
            'value' => 'v2',
            'is_public' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame('v2', $setting->fresh()->value);
        $this->assertSame('v2', Setting::get('cached_key'));
    }

    public function test_super_admin_can_delete_a_setting(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $setting = Setting::create(['key' => 'removable', 'value' => 'x', 'type' => 'string']);

        $this->actingAs($admin)->delete("/settings/{$setting->id}")->assertRedirect();

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
    }

    public function test_fo_admin_cannot_manage_settings(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $setting = Setting::create(['key' => 'foo', 'value' => 'x', 'type' => 'string']);

        $this->actingAs($foAdmin)->post('/settings', ['key' => 'x', 'type' => 'string', 'is_public' => false])
            ->assertForbidden();
        $this->actingAs($foAdmin)->put("/settings/{$setting->id}", ['value' => 'y', 'is_public' => false])
            ->assertForbidden();
        $this->actingAs($foAdmin)->delete("/settings/{$setting->id}")
            ->assertForbidden();
    }

    public function test_index_returns_settings_payload(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        Setting::create(['key' => 'zeta', 'value' => 'z', 'type' => 'string']);
        Setting::create(['key' => 'alpha', 'value' => 'a', 'type' => 'string']);

        $this->actingAs($admin)->get('/settings')->assertInertia(fn (Assert $page) => $page
            ->component('Settings/General/Index')
            ->has('settings', 2)
            ->where('settings.0.key', 'alpha')
        );
    }
}
