<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admins can see what a recipient was actually sent. The body is captured at
 * send time, not re-rendered — templates are editable, so re-rendering would
 * answer "what would we send today?" rather than "what did we tell them?".
 */
class EmailLogContentTest extends TestCase
{
    use RefreshDatabase;

    private function template(): EmailTemplate
    {
        return EmailTemplate::create([
            'code' => 'assignment_confirmation',
            'name' => 'Assignment Confirmation',
            'subject' => 'Hello {member_name}',
            'body_html' => '<p>Dear {member_name}, you are assigned to {exam_name}.</p>',
            'body_plain' => 'Dear {member_name}, you are assigned to {exam_name}.',
            'variables' => ['member_name' => 'Member name', 'exam_name' => 'Examination'],
            'is_active' => true,
        ]);
    }

    private function send(): EmailLog
    {
        return app(NotificationMailer::class)->send(
            templateCode: 'assignment_confirmation',
            toEmail: 'juan@example.com',
            toName: 'Juan Dela Cruz',
            emailType: 'designation',
            data: ['member_name' => 'Juan Dela Cruz', 'exam_name' => 'March 2026 CSE-PPT'],
        );
    }

    public function test_the_rendered_body_is_stored_with_the_delivery(): void
    {
        Mail::fake();
        $template = $this->template();

        $log = $this->send();

        $this->assertSame('sent', $log->status);
        $this->assertSame('Hello Juan Dela Cruz', $log->subject);
        $this->assertStringContainsString('Dear Juan Dela Cruz', $log->body_html);
        $this->assertStringContainsString('March 2026 CSE-PPT', $log->body_html);
        $this->assertSame($template->id, $log->email_template_id);
    }

    /**
     * The reason for storing rather than re-rendering: editing the template
     * must not rewrite history.
     */
    public function test_editing_the_template_does_not_alter_past_deliveries(): void
    {
        Mail::fake();
        $template = $this->template();
        $log = $this->send();

        $template->update(['body_html' => '<p>Completely different wording.</p>']);

        $this->assertStringContainsString('Dear Juan Dela Cruz', $log->fresh()->body_html);
    }

    /** A skipped send still records what would have gone out. */
    public function test_a_skipped_email_still_records_its_content(): void
    {
        Mail::fake();
        $this->template();
        Setting::set('email_sending_enabled', false);

        $log = $this->send();

        $this->assertSame('skipped', $log->status);
        $this->assertStringContainsString('Dear Juan Dela Cruz', $log->body_html);
    }

    public function test_an_admin_can_open_the_sent_content(): void
    {
        Mail::fake();
        $this->template();
        $log = $this->send();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->getJson("/email-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('subject', 'Hello Juan Dela Cruz')
            ->assertJsonPath('recipient_email', 'juan@example.com')
            ->assertJsonFragment(['body_html' => $log->body_html]);
    }

    /**
     * These bodies carry recipients' names and signed links, so they follow the
     * templates' own audience rather than being open to all staff.
     */
    public function test_other_staff_cannot_read_sent_email_content(): void
    {
        Mail::fake();
        $this->template();
        $log = $this->send();

        foreach ([UserRole::FoAdmin, UserRole::FieldDirector, UserRole::DirectorIv, UserRole::Member] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson("/email-logs/{$log->id}")
                ->assertForbidden();
        }
    }

    public function test_guests_cannot_read_sent_email_content(): void
    {
        Mail::fake();
        $this->template();
        $log = $this->send();

        // A web route, so an unauthenticated request is redirected to login
        // rather than answered with 401.
        $this->get("/email-logs/{$log->id}")->assertRedirect('/login');
    }

    public function test_the_templates_page_lists_the_delivery_log(): void
    {
        Mail::fake();
        $this->template();
        $log = $this->send();

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get('/email-templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/EmailTemplates/Index')
                ->where('logs.data.0.id', $log->id)
                ->where('logs.data.0.recipient_email', 'juan@example.com')
                ->where('logs.data.0.has_body', true)
                // The list stays light — bodies are fetched per row.
                ->missing('logs.data.0.body_html'));
    }

    public function test_the_log_can_be_filtered_by_recipient(): void
    {
        Mail::fake();
        $this->template();
        $this->send();

        EmailLog::create([
            'recipient_email' => 'maria@example.com',
            'recipient_name' => 'Maria Santos',
            'subject' => 'Something else',
            'email_type' => 'designation',
            'status' => 'sent',
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get('/email-templates?log_search=maria')
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.recipient_email', 'maria@example.com'));
    }

    /** Rows written before the body columns existed remain listed, just unopenable. */
    public function test_a_legacy_row_without_a_body_is_flagged(): void
    {
        $log = EmailLog::create([
            'recipient_email' => 'old@example.com',
            'subject' => 'Legacy',
            'email_type' => 'designation',
            'status' => 'sent',
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get('/email-templates')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data.0.id', $log->id)
                ->where('logs.data.0.has_body', false));
    }
}
