<?php

namespace Tests\Feature;

use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        foreach ([UserRole::Management, UserRole::FieldDirector] as $role) {
            $user = $this->staff($role, $role->isFieldOfficeScoped() ? $this->leyte : null);

            $this->actingAs($user)->get("/members/{$member->id}")->assertRedirect('/members');
            $this->actingAs($user)->post('/members', $this->memberPayload($this->leyte, ['email' => "x{$role->value}@example.com"]))
                ->assertForbidden();
            $this->actingAs($user)->delete("/members/{$member->id}")->assertForbidden();
        }

        $this->actingAs($this->staff(UserRole::Member))->get('/members')->assertForbidden();
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
}
