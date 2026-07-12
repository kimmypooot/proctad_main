<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\User;
use App\Notifications\AssignmentDeclined;
use App\Notifications\CertificateDecided;
use App\Notifications\CertificatePendingApproval;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_pending_certificate_notifies_the_matching_approver(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $otherFoDirector = User::factory()->create(['role' => UserRole::FieldDirector]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);

        Notification::assertSentTo($fieldDirector, CertificatePendingApproval::class);
        Notification::assertSentTo($superAdmin, CertificatePendingApproval::class);
        Notification::assertNotSentTo($otherFoDirector, CertificatePendingApproval::class);
    }

    public function test_pending_certificate_is_not_re_notified_on_duplicate_call(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        $service = app(CertificateService::class);
        $service->generatePending(CertificateType::Appearance, $assignment, $requester);
        $service->generatePending(CertificateType::Appearance, $assignment, $requester);

        Notification::assertSentToTimes($requester, CertificatePendingApproval::class, 0);
        Notification::assertCount(1);
    }

    public function test_releasing_a_certificate_notifies_the_requester(): void
    {
        Mail::fake();
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $approver = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        $certificate = app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);
        app(CertificateService::class)->release($certificate, $approver);

        Notification::assertSentTo($requester, CertificateDecided::class);
    }

    public function test_disapproving_a_certificate_notifies_the_requester(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $approver = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        $certificate = app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);
        app(CertificateService::class)->disapprove($certificate, $approver, 'Missing signature');

        Notification::assertSentTo($requester, CertificateDecided::class);
    }

    public function test_declining_an_assignment_notifies_the_field_office(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $otherFoAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $assignment = ExamAssignment::factory()->create([
            'field_office_id' => $fo->id,
            'status' => \App\Enums\AssignmentStatus::Pending,
        ]);

        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);
        $this->post($url, ['action' => 'decline', 'decline_reason' => 'Conflicting schedule'])->assertRedirect();

        Notification::assertSentTo($foAdmin, AssignmentDeclined::class);
        Notification::assertSentTo($fieldDirector, AssignmentDeclined::class);
        Notification::assertNotSentTo($otherFoAdmin, AssignmentDeclined::class);
    }

    public function test_confirming_an_assignment_does_not_notify_the_field_office(): void
    {
        Notification::fake();

        $fo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create([
            'field_office_id' => $fo->id,
            'status' => \App\Enums\AssignmentStatus::Pending,
        ]);

        $url = URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), ['assignment' => $assignment->id]);
        $this->post($url, ['action' => 'confirm'])->assertRedirect();

        Notification::assertNotSentTo($foAdmin, AssignmentDeclined::class);
    }

    public function test_notification_bell_prop_reflects_unread_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertInertia(fn ($page) => $page->where('notifications.unread_count', 1));

        $notification = $user->notifications()->first();

        $this->actingAs($user)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertInertia(fn ($page) => $page->where('notifications.unread_count', 0));
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        foreach (range(1, 3) as $i) {
            $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);
            app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);
        }

        $this->assertSame(3, $user->unreadNotifications()->count());

        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $intruder = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $assignment = ExamAssignment::factory()->create(['field_office_id' => $fo->id]);

        app(CertificateService::class)->generatePending(CertificateType::Appearance, $assignment, $requester);
        $notification = $owner->notifications()->first();

        $this->actingAs($intruder)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNull($notification->fresh()->read_at);
    }
}
