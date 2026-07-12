<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CertificatePagesTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(CertificateType $type, CertificateStatus $status, ?int $fieldOfficeId = null): Certificate
    {
        $assignment = ExamAssignment::factory()->create(
            $fieldOfficeId ? ['field_office_id' => $fieldOfficeId] : [],
        );

        return Certificate::create([
            'type' => $type,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => $status,
            'requested_by' => User::factory()->create()->id,
        ]);
    }

    public function test_certificates_index_renders_and_filters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending);
        $this->certificate(CertificateType::Appreciation, CertificateStatus::Released);

        $this->actingAs($admin)
            ->get('/certificates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Certificates/Index')
                ->has('certificates.data', 2));

        $this->actingAs($admin)
            ->get('/certificates?status=released')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1));
    }

    public function test_member_cannot_view_certificates_index(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/certificates')->assertForbidden();
    }

    public function test_approvals_index_shows_scoped_pending_requests(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();

        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $otherFo->id);
        $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending, $fo->id);

        $this->actingAs($fieldDirector)
            ->get('/approvals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Approvals/Index')
                ->has('pending', 1));
    }

    public function test_management_sees_only_appreciation_approvals(): void
    {
        $management = User::factory()->create(['role' => UserRole::Management]);
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending);
        $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending);

        $this->actingAs($management)
            ->get('/approvals')
            ->assertInertia(fn (Assert $page) => $page->has('pending', 1));
    }

    public function test_field_director_can_approve_from_the_page_flow(): void
    {
        $fo = FieldOffice::factory()->create();
        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $certificate = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);

        $this->actingAs($fieldDirector)
            ->post("/certificates/{$certificate->id}/approve")
            ->assertRedirect();

        $this->assertSame(CertificateStatus::Released, $certificate->fresh()->status);
    }
}
