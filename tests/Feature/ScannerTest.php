<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_raw_id_and_full_verify_url(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $esd = User::factory()->create(['role' => UserRole::EsdAdmin]);

        foreach ([$member->proctad_id, url("/verify/{$member->proctad_id}")] as $code) {
            $this->actingAs($esd)
                ->get('/scanner?code='.urlencode($code))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Scanner/Index')
                    ->where('result.proctad_id', $member->proctad_id));
        }
    }

    public function test_fo_admin_cannot_look_up_members_of_other_field_offices(): void
    {
        $leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
        $member = Member::factory()->create(['field_office_id' => $samar->id]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $leyte->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', null)
                ->where('notFound', true));
    }

    public function test_scan_with_examination_confirms_attendance_once(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = \App\Models\Examination::factory()->create();
        $assignment = \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
            'role' => 'proctor', // a non-coverage role: no venue selection required to confirm
        ]);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'confirmed'));

        $assignment->refresh();
        $this->assertNotNull($assignment->attendance_confirmed_at);
        $this->assertSame($admin->id, $assignment->attendance_confirmed_by);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'already_confirmed'));
    }

    public function test_scan_reports_not_assigned_when_member_has_no_assignment(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = \App\Models\Examination::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}&examination_id={$exam->id}")
            ->assertInertia(fn (Assert $page) => $page->where('attendance.outcome', 'not_assigned'));
    }

    public function test_scanner_is_limited_to_scanning_roles(): void
    {
        foreach ([UserRole::Member, UserRole::FieldDirector, UserRole::Management] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/scanner')
                ->assertForbidden();
        }
    }

    public function test_attendance_summary_reflects_present_and_absent_counts(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $exam = \App\Models\Examination::factory()->create();
        $present = \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'field_office_id' => $office->id,
            'attendance_confirmed_at' => now(),
        ]);
        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'field_office_id' => $office->id,
        ]);
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($admin)
            ->get("/scanner?examination_id={$exam->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendanceSummary.total', 2)
                ->where('attendanceSummary.present', 1)
                ->where('attendanceSummary.absent', 1)
                ->where('attendanceSummary.recent.0.id', "member:{$present->id}"));
    }

    public function test_bulk_mark_attendance_marks_only_unconfirmed_members(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $exam = \App\Models\Examination::factory()->create();
        $a = \App\Models\ExamAssignment::factory()->create(['examination_id' => $exam->id, 'field_office_id' => $office->id]);
        $b = \App\Models\ExamAssignment::factory()->create(['examination_id' => $exam->id, 'field_office_id' => $office->id]);
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->actingAs($admin)
            ->post('/scanner/mark-attendance', [
                'type' => 'exam',
                'examination_id' => $exam->id,
                'member_ids' => [$a->member_id, $b->member_id],
            ])
            ->assertRedirect();

        $this->assertNotNull($a->fresh()->attendance_confirmed_at);
        $this->assertNotNull($b->fresh()->attendance_confirmed_at);
    }
}
