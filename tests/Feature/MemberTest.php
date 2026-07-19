<?php

namespace Tests\Feature;

use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use App\Notifications\MemberRequirementReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $leyte;

    private FieldOffice $samar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leyte = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->samar = FieldOffice::create(['name' => 'Samar Field Office', 'code' => 'SAM']);
    }

    private function staff(UserRole $role, ?FieldOffice $office = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'field_office_id' => $office?->id,
        ]);
    }

    private function memberPayload(FieldOffice $office, array $overrides = []): array
    {
        return [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => null,
            'sex' => 'male',
            'date_of_birth' => '1990-05-15',
            'email' => 'juan.delacruz@example.com',
            'mobile_number' => '09171234567',
            'agency' => 'DepEd Division Office',
            'position' => 'Teacher III',
            'field_office_id' => $office->id,
            ...$overrides,
        ];
    }

    public function test_store_mints_proctad_id_and_creates_account_and_requirements(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte))
            ->assertRedirect();

        $member = Member::firstOrFail();

        $this->assertMatchesRegularExpression('/^PROCTAD-CSCRO8-[A-HJ-NP-Z2-9]{6}$/', $member->proctad_id);
        $this->assertCount(count(EligibilityRequirement::cases()), $member->requirements);
        $this->assertNotNull($member->user);
        $this->assertSame(UserRole::Member, $member->user->role);
        $this->assertSame('juan.delacruz@example.com', $member->user->email);
    }

    public function test_fo_admin_cannot_manage_members_of_another_field_office(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $other = Member::factory()->create(['field_office_id' => $this->samar->id]);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->samar))
            ->assertSessionHasErrors('field_office_id');

        $this->actingAs($admin)->get("/members/{$other->id}")->assertForbidden();
        // Payload passes validation (own FO), but the target member belongs to
        // another FO, so the policy rejects the update.
        $this->actingAs($admin)->put("/members/{$other->id}", [
            ...$this->memberPayload($this->leyte, ['email' => $other->email]),
            'status' => MemberStatus::Active->value,
        ])->assertForbidden();
        $this->actingAs($admin)->delete("/members/{$other->id}")->assertForbidden();
    }

    public function test_index_is_scoped_to_own_field_office_for_fo_roles(): void
    {
        Member::factory()->count(2)->create(['field_office_id' => $this->leyte->id]);
        Member::factory()->create(['field_office_id' => $this->samar->id]);

        $this->actingAs($this->staff(UserRole::FoAdmin, $this->leyte))
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Index')
                ->has('members.data', 2)
                ->where('fieldOffices', null));

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 3)
                ->has('fieldOffices', 2));
    }

    public function test_index_search_and_status_filter(): void
    {
        Member::factory()->create([
            'field_office_id' => $this->leyte->id,
            'last_name' => 'Zabala',
            'status' => MemberStatus::Disqualified,
        ]);
        Member::factory()->create(['field_office_id' => $this->leyte->id, 'last_name' => 'Abad']);

        $esd = $this->staff(UserRole::EsdAdmin);

        $this->actingAs($esd)
            ->get('/members?search=Zabala')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1));

        $this->actingAs($esd)
            ->get('/members?status=disqualified')
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', fn ($name) => str_contains($name, 'ZABALA')));
    }

    public function test_view_only_roles_cannot_modify(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);

        // Management is region-wide oversight only. Field Directors run their own
        // Testing Center's operations and are covered separately below.
        $user = $this->staff(UserRole::Management);

        $this->actingAs($user)->get("/members/{$member->id}")->assertRedirect('/members');
        $this->actingAs($user)->post('/members', $this->memberPayload($this->leyte, ['email' => 'x-management@example.com']))
            ->assertForbidden();
        $this->actingAs($user)->delete("/members/{$member->id}")->assertForbidden();

        $this->actingAs($this->staff(UserRole::Member))->get('/members')->assertForbidden();
    }

    /**
     * Field Directors operate their own Testing Center alongside FO Admin staff.
     * The scoping half matters most: every controller scope keys off
     * isFieldOfficeScoped(), so a Director must not reach another office's data.
     */
    public function test_field_director_manages_own_testing_center_only(): void
    {
        $director = $this->staff(UserRole::FieldDirector, $this->leyte);

        $own = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $other = Member::factory()->create(['field_office_id' => $this->samar->id]);

        // Can create and edit within their own office.
        $this->actingAs($director)
            ->post('/members', $this->memberPayload($this->leyte, ['email' => 'fd-created@example.com']))
            ->assertRedirect();

        // show() always redirects to the index; details() is the real read.
        $this->actingAs($director)->get("/members/{$own->id}/details")->assertOk();
        $this->actingAs($director)->delete("/members/{$own->id}")->assertRedirect();

        // But not another Testing Center's. Deleting is blocked by the policy;
        // creating is blocked one layer earlier, by the field-office scope rule
        // on StoreMemberRequest — different mechanisms, same boundary.
        $this->actingAs($director)->delete("/members/{$other->id}")->assertForbidden();

        $this->actingAs($director)
            ->post('/members', $this->memberPayload($this->samar, ['email' => 'fd-crossoffice@example.com']))
            ->assertSessionHasErrors('field_office_id');

        $this->assertDatabaseMissing('members', ['email' => 'fd-crossoffice@example.com']);
    }

    public function test_requirement_update_with_file_upload_and_download(): void
    {
        Storage::fake('local');

        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $key = EligibilityRequirement::UpdatedPds->value;

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/{$key}", [
                'complied' => true,
                'remarks' => 'Certified by HRMO',
                'file' => UploadedFile::fake()->create('pds.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $record = $member->requirements()->where('requirement', $key)->firstOrFail();
        $this->assertTrue($record->complied);
        Storage::disk('local')->assertExists($record->file_path);

        $this->actingAs($admin)
            ->get("/members/{$member->id}/requirements/{$key}/download")
            ->assertOk()
            ->assertDownload("{$member->proctad_id}-{$key}.pdf");
    }

    /**
     * Members can submit documents but cannot mark themselves compliant, so the
     * outcome of a review is otherwise invisible until they reopen their profile.
     */
    public function test_reviewing_a_requirement_notifies_the_member(): void
    {
        Notification::fake();

        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id, 'user_id' => $user->id]);
        $key = EligibilityRequirement::UpdatedPds->value;

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/{$key}", ['complied' => true])
            ->assertRedirect();

        Notification::assertSentTo($user, MemberRequirementReviewed::class);
    }

    public function test_rejecting_with_remarks_also_notifies(): void
    {
        Notification::fake();

        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id, 'user_id' => $user->id]);
        $key = EligibilityRequirement::UpdatedPds->value;

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/{$key}", [
                'complied' => false,
                'remarks' => 'Signature page missing.',
            ])
            ->assertRedirect();

        Notification::assertSentTo($user, MemberRequirementReviewed::class);
    }

    /** Re-saving an unchanged row is not a review, and must not ping the member. */
    public function test_saving_a_requirement_unchanged_does_not_notify(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id, 'user_id' => $user->id]);
        $key = EligibilityRequirement::UpdatedPds->value;

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/{$key}", ['complied' => true, 'remarks' => 'Verified']);

        Notification::fake();

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/{$key}", ['complied' => true, 'remarks' => 'Verified'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    /** A member with no linked account has nowhere to receive it — must not error. */
    public function test_reviewing_a_requirement_for_an_unlinked_member_is_safe(): void
    {
        Notification::fake();

        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id, 'user_id' => null]);

        $this->actingAs($admin)
            ->put("/members/{$member->id}/requirements/".EligibilityRequirement::UpdatedPds->value, ['complied' => true])
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_index_exposes_last_exam_served(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $exam = \App\Models\Examination::factory()->create(['exam_date' => '2026-03-15']);

        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $this->leyte->id,
            'attendance_confirmed_at' => '2026-03-15 06:30:00',
        ]);

        $this->actingAs($this->staff(UserRole::EsdAdmin))
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.data.0.last_served.title', $exam->title));
    }

    public function test_photo_upload_on_store_and_replacement_on_update(): void
    {
        Storage::fake('local');

        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', [
                ...$this->memberPayload($this->leyte),
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertRedirect();

        $member = Member::firstOrFail();
        $firstPath = $member->photo_path;
        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);

        $this->actingAs($admin)
            ->put("/members/{$member->id}", [
                ...$this->memberPayload($this->leyte, ['email' => $member->email]),
                'status' => MemberStatus::Active->value,
                'photo' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertNotSame($firstPath, $member->photo_path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($member->photo_path);
    }

    public function test_disqualification_requires_remarks_and_soft_delete_reserves_id(): void
    {
        $admin = $this->staff(UserRole::EsdAdmin);
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        $payload = $this->memberPayload($this->leyte, ['email' => $member->email]);

        $this->actingAs($admin)
            ->put("/members/{$member->id}", [...$payload, 'status' => 'disqualified'])
            ->assertSessionHasErrors('disqualification_remarks');

        $this->actingAs($admin)
            ->put("/members/{$member->id}", [
                ...$payload,
                'status' => 'disqualified',
                'disqualification_remarks' => 'Final administrative case on record.',
            ])
            ->assertRedirect();

        $this->assertSame(MemberStatus::Disqualified, $member->fresh()->status);

        $proctadId = $member->proctad_id;
        $this->actingAs($admin)->delete("/members/{$member->id}")->assertRedirect('/members');
        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertTrue(Member::withTrashed()->where('proctad_id', $proctadId)->exists());
    }

    public function test_bulk_id_card_download_requires_an_explicit_id_list(): void
    {
        Member::factory()->count(3)->create(['field_office_id' => $this->leyte->id]);
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        // An absent or empty list must not fall through to "every member".
        $this->actingAs($admin)
            ->postJson('/members/id-cards/download-bulk', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.ids.0', 'The ids field is required.');

        $this->actingAs($admin)
            ->postJson('/members/id-cards/download-bulk', ['ids' => []])
            ->assertStatus(422);
    }

    public function test_bulk_id_card_download_is_bounded_and_scoped_to_the_requesters_office(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $own = Member::factory()->count(2)->create(['field_office_id' => $this->leyte->id]);
        $other = Member::factory()->create(['field_office_id' => $this->samar->id]);

        $this->actingAs($admin)
            ->postJson('/members/id-cards/download-bulk', ['ids' => range(1, 201)])
            ->assertStatus(422);

        // A member outside the requester's office must 403, not be silently omitted.
        $this->actingAs($admin)
            ->postJson('/members/id-cards/download-bulk', ['ids' => [$own[0]->id, $other->id]])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('/members/id-cards/download-bulk', ['ids' => $own->pluck('id')->all()])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
