<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

        foreach ([UserRole::FoAdmin, UserRole::FieldDirector, UserRole::DirectorIv, UserRole::DirectorIii] as $role) {
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

    /**
     * The page is used by Testing Center staff, not developers — every setting
     * has to arrive with a plain-language label and an explanation, including
     * custom keys nobody has written copy for.
     */
    public function test_settings_payload_carries_plain_language_labels(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        Setting::set(Setting::EMAIL_SENDING_ENABLED, true, 'boolean');
        Setting::create(['key' => 'custom_widget_id', 'value' => 'x', 'type' => 'string']);

        $this->actingAs($admin)->get('/settings')->assertInertia(function (Assert $page) {
            $settings = collect($page->toArray()['props']['settings']);

            $email = $settings->firstWhere('key', Setting::EMAIL_SENDING_ENABLED);
            $this->assertSame('Send emails', $email['label']);
            $this->assertSame('Email', $email['group']);
            $this->assertSame('toggle', $email['control']);
            $this->assertNotEmpty($email['help']);

            // Uncatalogued key still renders readably rather than as raw snake_case.
            $custom = $settings->firstWhere('key', 'custom_widget_id');
            $this->assertSame('Custom widget ID', $custom['label']);
            $this->assertSame('Other', $custom['group']);
        });
    }

    public function test_email_sending_defaults_to_enabled_when_the_setting_is_absent(): void
    {
        // A missing row must never silently swallow mail — it has to be
        // switched off deliberately.
        $this->assertTrue(Setting::emailSendingEnabled());
    }

    /**
     * The kill switch redirects the default mailer to 'log' in AppServiceProvider,
     * which is what catches senders that call Mail:: directly rather than going
     * through NotificationMailer.
     */
    public function test_pausing_email_redirects_the_mailer_away_from_smtp(): void
    {
        config(['mail.default' => 'smtp']);
        Setting::set(Setting::EMAIL_SENDING_ENABLED, false, 'boolean');

        // Re-boot the provider the way a fresh request would.
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_paused_email_is_logged_as_skipped_and_not_sent(): void
    {
        Mail::fake();
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);

        Setting::set(Setting::EMAIL_SENDING_ENABLED, false, 'boolean');

        $log = app(\App\Services\NotificationMailer::class)->send(
            templateCode: 'assignment_confirmation',
            toEmail: 'member@proctad.test',
            toName: 'Test Member',
            emailType: 'designation',
            data: [],
        );

        $this->assertSame('skipped', $log->status);
        Mail::assertNothingSent();

        // And back on: the same call sends normally.
        Setting::set(Setting::EMAIL_SENDING_ENABLED, true, 'boolean');

        $log = app(\App\Services\NotificationMailer::class)->send(
            templateCode: 'assignment_confirmation',
            toEmail: 'member@proctad.test',
            toName: 'Test Member',
            emailType: 'designation',
            data: [],
        );

        $this->assertSame('sent', $log->status);
        Mail::assertSent(\App\Mail\TemplatedMail::class);
    }
}
