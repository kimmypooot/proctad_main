<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Enums\UserRole;
use App\Mail\TemplatedMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AssignmentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    private function assignmentFor(FieldOffice $fo): ExamAssignment
    {
        $member = Member::factory()->create(['field_office_id' => $fo->id, 'email' => 'member@proctad.test']);

        return ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'field_office_id' => $fo->id,
        ]);
    }

    public function test_staff_can_send_confirmation_request(): void
    {
        Mail::fake();

        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->assignmentFor($fo);

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/send-confirmation")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Pending, $assignment->status);
        $this->assertNotNull($assignment->confirmation_sent_at);
        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::Sent)->count());
        $this->assertSame(1, EmailLog::where('status', 'sent')->count());

        Mail::assertSent(TemplatedMail::class);
    }

    /**
     * NotificationMailer swallows delivery failures into email_logs instead of
     * throwing, so a failed send used to flash "Confirmation request sent" and
     * stamp confirmation_sent_at — the exact blind spot that made a broken
     * mailer look like a working one.
     */
    public function test_failed_send_reports_an_error_and_does_not_mark_it_sent(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = $this->assignmentFor($fo);

        // Deactivating the template makes NotificationMailer log a failure.
        EmailTemplate::where('code', 'assignment_confirmation')->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/send-confirmation")
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $assignment->refresh();
        $this->assertNull($assignment->confirmation_sent_at);
        $this->assertSame(0, $assignment->confirmations()->where('action', ConfirmationAction::Sent)->count());
        $this->assertSame(1, EmailLog::where('status', 'failed')->count());
    }

    public function test_sending_confirmation_requires_permission(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $otherFo->id]);
        $assignment = $this->assignmentFor($fo);

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/send-confirmation")
            ->assertForbidden();
    }

    public function test_member_without_email_cannot_be_sent_a_confirmation(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $member = Member::factory()->create(['field_office_id' => $fo->id, 'email' => '']);
        $assignment = ExamAssignment::factory()->create(['member_id' => $member->id, 'field_office_id' => $fo->id]);

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/send-confirmation")
            ->assertRedirect();

        $this->assertNull($assignment->fresh()->confirmation_sent_at);
    }

    public function test_signed_link_shows_confirmation_page(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->get($url)->assertOk();
    }

    public function test_tampered_link_is_rejected(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $this->get("/assignments/{$assignment->id}/confirm?expires=9999999999&signature=bogus")
            ->assertForbidden();
    }

    /** A member clicking a week-old email must land on an explanation, not the stock 403. */
    public function test_expired_link_renders_the_link_expired_page(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->travel(8)->days();

        $this->get($url)
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('Errors/LinkExpired'));
    }

    public function test_confirmation_page_shows_the_response_deadline(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $expiry = now()->addDays(7);
        $url = URL::temporarySignedRoute('assignments.confirm', $expiry, ['assignment' => $assignment->id]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('responseDueBy', $expiry->format('F j, Y')));
    }

    /** The room is withheld until exam day — it must not reach this public page at all. */
    public function test_confirmation_page_never_exposes_the_room(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('assignment.room'));
    }

    public function test_member_can_confirm_via_signed_link(): void
    {
        $assignment = ExamAssignment::factory()->create(['status' => AssignmentStatus::Pending]);
        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->post($url, ['action' => 'confirm'])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Confirmed, $assignment->status);
        $this->assertNotNull($assignment->responded_at);
        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::Confirmed)->count());
    }

    public function test_member_can_decline_with_reason_via_signed_link(): void
    {
        $assignment = ExamAssignment::factory()->create(['status' => AssignmentStatus::Pending]);
        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->post($url, ['action' => 'decline', 'decline_reason' => 'Conflicting schedule'])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Declined, $assignment->status);
        $this->assertSame('Conflicting schedule', $assignment->decline_reason);
        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::Declined)->count());
    }

    public function test_decline_requires_a_reason(): void
    {
        $assignment = ExamAssignment::factory()->create(['status' => AssignmentStatus::Pending]);
        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->post($url, ['action' => 'decline'])->assertSessionHasErrors('decline_reason');

        $this->assertSame(AssignmentStatus::Pending, $assignment->fresh()->status);
    }

    public function test_already_responded_assignment_cannot_be_reconfirmed(): void
    {
        $assignment = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Confirmed,
            'responded_at' => now(),
        ]);
        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);

        $this->post($url, ['action' => 'decline', 'decline_reason' => 'Changed my mind'])
            ->assertSessionHas('error');

        $this->assertSame(AssignmentStatus::Confirmed, $assignment->fresh()->status);
    }

    public function test_reminder_command_emails_only_stale_unanswered_assignments(): void
    {
        Mail::fake();

        $dueForReminder = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now()->subDays(4),
        ]);
        $tooRecent = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now()->subDay(),
        ]);
        $alreadyConfirmed = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Confirmed,
            'confirmation_sent_at' => now()->subDays(4),
            'responded_at' => now(),
        ]);

        $this->artisan('proctad:send-assignment-reminders')->assertSuccessful();

        $this->assertSame(1, $dueForReminder->confirmations()->where('action', ConfirmationAction::ReminderSent)->count());
        $this->assertSame(0, $tooRecent->confirmations()->where('action', ConfirmationAction::ReminderSent)->count());
        $this->assertSame(0, $alreadyConfirmed->confirmations()->where('action', ConfirmationAction::ReminderSent)->count());
    }

    public function test_reminder_command_does_not_send_twice(): void
    {
        Mail::fake();

        $assignment = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now()->subDays(4),
        ]);

        $this->artisan('proctad:send-assignment-reminders');
        $this->artisan('proctad:send-assignment-reminders');

        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::ReminderSent)->count());
    }

    public function test_expire_command_marks_stale_pending_assignments(): void
    {
        $stale = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now()->subDays(8),
        ]);
        $fresh = ExamAssignment::factory()->create([
            'status' => AssignmentStatus::Pending,
            'confirmation_sent_at' => now()->subDays(2),
        ]);

        $this->artisan('proctad:expire-pending-assignments')->assertSuccessful();

        $this->assertSame(AssignmentStatus::Expired, $stale->fresh()->status);
        $this->assertSame(AssignmentStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(1, $stale->confirmations()->where('action', ConfirmationAction::Expired)->count());
    }

    public function test_email_template_render_substitutes_placeholders(): void
    {
        $template = EmailTemplate::where('code', 'assignment_confirmation')->firstOrFail();

        $rendered = $template->render([
            'member_name' => 'Juan Dela Cruz',
            'exam_name' => 'CSE-PPT',
            'exam_date' => 'August 9, 2026',
            'designation' => 'Proctor',
            'proctad_id' => 'PROCTAD-CSCRO8-ABC123',
            'confirmation_url' => 'https://example.test/confirm',
        ]);

        $this->assertSame('Confirmation Required: CSE-PPT - August 9, 2026', $rendered['subject']);
        $this->assertStringContainsString('Juan Dela Cruz', $rendered['html']);
        $this->assertStringContainsString('https://example.test/confirm', $rendered['html']);
    }
}
