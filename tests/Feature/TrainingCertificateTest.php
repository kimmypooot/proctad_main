<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\TrainingType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Which certificate a training earns depends on its type. Every training
 * issues a Certificate of Appearance on confirmed attendance; only a TEA — a
 * course with something to complete — also issues a Certificate of Completion.
 * A Briefing on Conduct of Examination never does.
 */
class TrainingCertificateTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $office;

    private User $admin;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->admin = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->office->id,
        ]);
        $this->member = Member::factory()->create(['field_office_id' => $this->office->id]);
    }

    private function training(TrainingType $type): Training
    {
        return Training::factory()->create([
            'type' => $type,
            'field_office_id' => $this->office->id,
        ]);
    }

    private function assign(Training $training, ?string $confirmedAt = null): TrainingAssignment
    {
        return TrainingAssignment::factory()->create([
            'training_id' => $training->id,
            'member_id' => $this->member->id,
            'field_office_id' => $this->office->id,
            'attendance_confirmed_at' => $confirmedAt,
        ]);
    }

    private function scanInto(Training $training): void
    {
        $this->assign($training);

        $this->actingAs($this->admin)
            ->get("/scanner?code={$this->member->proctad_id}&training_id={$training->id}")
            ->assertOk();
    }

    /** @return array<string> */
    private function certificateTypes(): array
    {
        return Certificate::pluck('type')
            ->map(fn ($type) => $type instanceof CertificateType ? $type->value : $type)
            ->sort()
            ->values()
            ->all();
    }

    public function test_scanning_into_a_tea_issues_both_appearance_and_completion(): void
    {
        $this->scanInto($this->training(TrainingType::Tea));

        $this->assertSame(['appearance', 'completion'], $this->certificateTypes());
    }

    public function test_scanning_into_a_briefing_issues_appearance_only(): void
    {
        $this->scanInto($this->training(TrainingType::Briefing));

        $this->assertSame(['appearance'], $this->certificateTypes());
    }

    public function test_bulk_marking_a_tea_issues_both_certificates(): void
    {
        $training = $this->training(TrainingType::Tea);
        $this->assign($training);

        $this->actingAs($this->admin)->post('/scanner/mark-attendance', [
            'type' => 'training',
            'training_id' => $training->id,
            'member_ids' => [$this->member->id],
        ])->assertRedirect();

        $this->assertSame(['appearance', 'completion'], $this->certificateTypes());
    }

    public function test_bulk_marking_a_briefing_issues_appearance_only(): void
    {
        $training = $this->training(TrainingType::Briefing);
        $this->assign($training);

        $this->actingAs($this->admin)->post('/scanner/mark-attendance', [
            'type' => 'training',
            'training_id' => $training->id,
            'member_ids' => [$this->member->id],
        ])->assertRedirect();

        $this->assertSame(['appearance'], $this->certificateTypes());
    }

    /**
     * Concluding a briefing must not quietly hand out the certificate the scan
     * deliberately withheld.
     */
    public function test_completing_a_briefing_issues_no_completion_certificate(): void
    {
        $training = $this->training(TrainingType::Briefing);
        $this->assign($training, now()->toDateTimeString());

        $this->actingAs($this->admin)
            ->post("/trainings/{$training->id}/complete")
            ->assertRedirect();

        $this->assertNotNull($training->fresh()->completed_at);
        $this->assertSame(0, Certificate::where('type', CertificateType::Completion)->count());
    }

    /**
     * The backstop: attendance recorded before this rule existed (or by any
     * path that skipped the scanner) still gets its Completion certificate
     * when the TEA is concluded.
     */
    public function test_completing_a_tea_backfills_a_missing_completion_certificate(): void
    {
        $training = $this->training(TrainingType::Tea);
        $this->assign($training, now()->toDateTimeString());

        $this->actingAs($this->admin)
            ->post("/trainings/{$training->id}/complete")
            ->assertRedirect();

        $this->assertSame(1, Certificate::where('type', CertificateType::Completion)->count());
    }

    /** Concluding a TEA whose attendees were scanned in must not duplicate. */
    public function test_completing_a_tea_does_not_duplicate_certificates_issued_at_scan_time(): void
    {
        $training = $this->training(TrainingType::Tea);
        $this->scanInto($training);

        $this->actingAs($this->admin)
            ->post("/trainings/{$training->id}/complete")
            ->assertRedirect();

        $this->assertSame(1, Certificate::where('type', CertificateType::Completion)->count());
        $this->assertSame(1, Certificate::where('type', CertificateType::Appearance)->count());
    }
}
