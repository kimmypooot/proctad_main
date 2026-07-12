<?php

namespace Tests\Feature;

use App\Models\AssignmentConfirmation;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\ExamAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOldLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_old_audit_logs_email_logs_confirmations_and_read_notifications(): void
    {
        $assignment = ExamAssignment::factory()->create();

        $oldAudit = AuditLog::create(['action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => 1]);
        $oldAudit->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        $recentAudit = AuditLog::create(['action' => 'login', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => 1]);

        $oldConfirmation = AssignmentConfirmation::create([
            'exam_assignment_id' => $assignment->id,
            'action' => 'sent',
        ]);
        $oldConfirmation->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        $recentConfirmation = AssignmentConfirmation::create([
            'exam_assignment_id' => $assignment->id,
            'action' => 'sent',
        ]);

        $oldEmailLog = EmailLog::create([
            'recipient_email' => 'old@proctad.test',
            'subject' => 'Old',
            'email_type' => 'designation',
            'status' => 'sent',
            'sent_at' => now()->subMonths(8),
        ]);
        $oldEmailLog->forceFill(['created_at' => now()->subMonths(8)])->saveQuietly();

        $recentEmailLog = EmailLog::create([
            'recipient_email' => 'recent@proctad.test',
            'subject' => 'Recent',
            'email_type' => 'designation',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $oldReadNotificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $oldReadNotificationId,
            'type' => 'App\\Notifications\\CertificateDecided',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => now()->subDays(120),
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        $recentReadNotificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $recentReadNotificationId,
            'type' => 'App\\Notifications\\CertificateDecided',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $unreadNotificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $unreadNotificationId,
            'type' => 'App\\Notifications\\CertificateDecided',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => null,
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        $this->artisan('proctad:prune-logs')->assertSuccessful();

        $this->assertModelMissing($oldAudit);
        $this->assertModelExists($recentAudit);
        $this->assertModelMissing($oldConfirmation);
        $this->assertModelExists($recentConfirmation);
        $this->assertModelMissing($oldEmailLog);
        $this->assertModelExists($recentEmailLog);
        $this->assertDatabaseMissing('notifications', ['id' => $oldReadNotificationId]);
        $this->assertDatabaseHas('notifications', ['id' => $recentReadNotificationId]);
        $this->assertDatabaseHas('notifications', ['id' => $unreadNotificationId]);
    }
}
