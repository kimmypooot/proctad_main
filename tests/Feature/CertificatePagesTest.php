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

        // Field Director sees both pending requests in their own Testing
        // Center — Appearance (primary approver) and Appreciation (local
        // fallback for Management) — but not the other FO's Appearance request.
        $this->actingAs($fieldDirector)
            ->get('/approvals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Approvals/Index')
                ->has('pending', 2));
    }

    /**
     * The sidebar badge and the approvals queue must count the same set — a badge
     * advertising work the user can't see when they click through is worse than
     * no badge. Both read Certificate::scopePendingApprovalFor().
     */
    public function test_sidebar_badge_matches_the_approvals_queue_per_role(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();

        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $otherFo->id);
        $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending, $fo->id);
        // Released ones must never be counted.
        $this->certificate(CertificateType::Appearance, CertificateStatus::Released, $fo->id);

        $cases = [
            // [user, expected count]
            [User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]), 2],
            // Either regional director approves Appreciation — the split into
            // Director IV / III is about the REC seat, not approval authority.
            [User::factory()->create(['role' => UserRole::DirectorIv]), 1],
            [User::factory()->create(['role' => UserRole::DirectorIii]), 1],
            [User::factory()->create(['role' => UserRole::SuperAdmin]), 3],
            // No approval rights — badge must stay hidden.
            [User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]), 0],
        ];

        foreach ($cases as [$user, $expected]) {
            $this->actingAs($user)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('pendingApprovalCount', $expected));

            // And the queue itself lists exactly that many, for roles that can open it.
            if ($expected > 0) {
                $this->actingAs($user)
                    ->get('/approvals')
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->has('pending', $expected));
            }
        }
    }

    public function test_field_director_can_approve_appreciation_as_a_local_fallback(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);

        $ownFo = $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending, $fo->id);
        $anotherFo = $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending, $otherFo->id);

        $this->actingAs($fieldDirector)
            ->post("/certificates/{$ownFo->id}/approve")
            ->assertRedirect();
        $this->assertSame(CertificateStatus::Released, $ownFo->fresh()->status);

        $this->actingAs($fieldDirector)
            ->post("/certificates/{$anotherFo->id}/approve")
            ->assertForbidden();
        $this->assertSame(CertificateStatus::Pending, $anotherFo->fresh()->status);
    }

    public function test_management_sees_only_appreciation_approvals(): void
    {
        $this->certificate(CertificateType::Appearance, CertificateStatus::Pending);
        $this->certificate(CertificateType::Appreciation, CertificateStatus::Pending);

        foreach ([UserRole::DirectorIv, UserRole::DirectorIii] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/approvals')
                ->assertInertia(fn (Assert $page) => $page->has('pending', 1));
        }
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

    public function test_esd_admin_is_a_fallback_approver(): void
    {
        $esdAdmin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $certificate = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending);

        $this->actingAs($esdAdmin)
            ->get('/approvals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pending', 1));

        $this->actingAs($esdAdmin)
            ->post("/certificates/{$certificate->id}/approve")
            ->assertRedirect();

        $this->assertSame(CertificateStatus::Released, $certificate->fresh()->status);
    }

    public function test_bulk_approve_releases_only_eligible_certificates(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $fieldDirector = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);

        $inScope = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);
        $outOfScope = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $otherFo->id);

        $this->actingAs($fieldDirector)
            ->post('/certificates/bulk-approve', [
                'certificate_ids' => [$inScope->id, $outOfScope->id],
            ])
            ->assertRedirect();

        $this->assertSame(CertificateStatus::Released, $inScope->fresh()->status);
        $this->assertSame(CertificateStatus::Pending, $outOfScope->fresh()->status);
    }

    public function test_bulk_disapprove_requires_a_shared_remarks_and_applies_to_all(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $first = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending);
        $second = $this->certificate(CertificateType::DesignationOrder, CertificateStatus::Pending);

        $this->actingAs($admin)
            ->post('/certificates/bulk-disapprove', ['certificate_ids' => [$first->id, $second->id]])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($admin)
            ->post('/certificates/bulk-disapprove', [
                'certificate_ids' => [$first->id, $second->id],
                'remarks' => 'Missing supporting documents.',
            ])
            ->assertRedirect();

        $this->assertSame(CertificateStatus::Disapproved, $first->fresh()->status);
        $this->assertSame(CertificateStatus::Disapproved, $second->fresh()->status);
        $this->assertSame('Missing supporting documents.', $second->fresh()->disapproval_remarks);
    }

    /**
     * Approving your own request is permitted — a Field Director has to be able
     * to cover for an absent FO Admin — but the queue flags it so it stays a
     * conscious act rather than something that passes unnoticed.
     */
    public function test_queue_flags_requests_the_approver_made_themselves(): void
    {
        $fo = FieldOffice::factory()->create();
        $director = User::factory()->create(['role' => UserRole::FieldDirector, 'field_office_id' => $fo->id]);
        $someoneElse = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);

        $own = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);
        $own->update(['requested_by' => $director->id]);

        $theirs = $this->certificate(CertificateType::Appearance, CertificateStatus::Pending, $fo->id);
        $theirs->update(['requested_by' => $someoneElse->id]);

        $this->actingAs($director)->get('/approvals')->assertInertia(function (Assert $page) use ($own, $theirs) {
            $rows = collect($page->toArray()['props']['pending']);

            $this->assertTrue($rows->firstWhere('id', $own->id)['self_requested']);
            $this->assertFalse($rows->firstWhere('id', $theirs->id)['self_requested']);
        });

        // Flagged, not blocked.
        $this->actingAs($director)->post("/certificates/{$own->id}/approve")->assertRedirect();
        $this->assertSame(CertificateStatus::Released, $own->fresh()->status);
    }
}
