<?php

namespace Tests\Feature;

use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use App\Notifications\MemberRequirementReviewed;
use App\Support\Workspace;
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
            'testing_center_id' => $this->centerFor($office)->id,
            ...$overrides,
        ];
    }

    /** A testing center the office handles, created on first use. */
    private function centerFor(FieldOffice $office): TestingCenter
    {
        return $office->testingCenters()->first()
            ?? TestingCenter::factory()->forFieldOffice($office)->create();
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

    /**
     * ?view=<id> carries an administrator from a member's user account straight
     * into their registry record. It only says which modal to open — the modal
     * loads through /members/{member}/details, which authorizes for itself — so
     * the id is echoed back without the list being narrowed to it.
     */
    public function test_index_accepts_a_member_to_open_on_arrival(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);
        Member::factory()->create(['field_office_id' => $this->leyte->id]);

        $esd = $this->staff(UserRole::EsdAdmin);

        $this->actingAs($esd)
            ->get("/members?view={$member->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewMemberId', $member->id)
                ->has('members.data', 2));

        $this->actingAs($esd)
            ->get('/members')
            ->assertInertia(fn (Assert $page) => $page->where('viewMemberId', null));
    }

    /**
     * Agency and position are printed beside the name on rosters and
     * designation orders, so they follow the name's casing. Without it the same
     * employer typed by two people reads as two agencies.
     */
    public function test_agency_and_position_are_stored_uppercase(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte, [
                'agency' => 'DepEd Division Office',
                'position' => 'Teacher III',
            ]))
            ->assertRedirect();

        $member = Member::firstOrFail();

        $this->assertSame('DEPED DIVISION OFFICE', $member->agency);
        $this->assertSame('TEACHER III', $member->position);
    }

    /** A blank position must stay null rather than becoming an empty string. */
    public function test_a_missing_position_is_left_null(): void
    {
        $member = Member::factory()->create([
            'field_office_id' => $this->leyte->id,
            'position' => null,
        ]);

        $this->assertNull($member->fresh()->position);
    }

    public function test_normalize_casing_command_backfills_existing_records(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);

        // Straight past the mutators, the way a legacy import would have landed.
        Member::withoutEvents(fn () => Member::where('id', $member->id)->update([
            'agency' => 'DepEd Division Office',
            'position' => 'Teacher III',
            'last_name' => 'Dela Cruz',
        ]));

        $this->artisan('proctad:normalize-name-casing')->assertSuccessful();

        $member->refresh();

        $this->assertSame('DEPED DIVISION OFFICE', $member->agency);
        $this->assertSame('TEACHER III', $member->position);
        $this->assertSame('DELA CRUZ', $member->last_name);
    }

    public function test_view_only_roles_cannot_modify(): void
    {
        $member = Member::factory()->create(['field_office_id' => $this->leyte->id]);

        // Management is region-wide oversight only. Field Directors run their own
        // Field Office's operations and are covered separately below.
        $user = $this->staff(UserRole::DirectorIv);

        $this->actingAs($user)->get("/members/{$member->id}")->assertRedirect('/members');
        $this->actingAs($user)->post('/members', $this->memberPayload($this->leyte, ['email' => 'x-management@example.com']))
            ->assertForbidden();
        $this->actingAs($user)->delete("/members/{$member->id}")->assertForbidden();

        $this->actingAs($this->staff(UserRole::Member))->get('/members')->assertForbidden();
    }

    public function test_details_carries_the_testing_center_for_the_view_modal(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $center = $this->centerFor($this->leyte);
        $member = Member::factory()->create([
            'field_office_id' => $this->leyte->id,
            'testing_center_id' => $center->id,
        ]);

        $this->actingAs($admin)
            ->get("/members/{$member->id}/details")
            ->assertOk()
            ->assertJsonPath('member.testing_center.name', $center->name)
            ->assertJsonPath('member.field_office.name', $this->leyte->name);
    }

    /**
     * Field Directors operate their own Field Office alongside FO Admin staff.
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

        // But not another Field Office's. Deleting is blocked by the policy;
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
        $exam = Examination::factory()->create(['exam_date' => '2026-03-15']);

        ExamAssignment::factory()->create([
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
        // Returns to wherever the detail modal was opened — it can sit over the
        // Users page as well as the Members list.
        $this->actingAs($admin)
            ->from('/users?tab=members')
            ->delete("/members/{$member->id}")
            ->assertRedirect('/users?tab=members');
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

    /**
     * The dual-hat path end to end. A CSC employee cannot self-register — the
     * registration flow turns away any email that already has a login — so
     * registering them here, against the account they already hold, is the only
     * way they ever get a PROCTAD record and the workspace switcher with it.
     */
    public function test_registering_an_existing_staff_account_links_it_without_changing_their_role(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $employee = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->leyte->id,
            'email' => 'proctoring.employee@csc.gov.ph',
        ]);

        $this->assertFalse(Workspace::availableTo($employee), 'No record yet, so no switcher.');

        // The form seeds itself from the account so the email cannot be retyped
        // wrong — a typo would mint a second login instead of linking this one.
        $this->actingAs($admin)
            ->getJson("/members/create?user={$employee->id}")
            ->assertOk()
            ->assertJsonPath('account.email', 'proctoring.employee@csc.gov.ph')
            ->assertJsonPath('account.id', $employee->id);

        // Registration is reached from the Users page as often as from the
        // members list, so it returns to whichever one started it rather than
        // dropping the admin onto /members with their filters cleared.
        $this->actingAs($admin)
            ->from('/users?tab=staff')
            ->post('/members', $this->memberPayload($this->leyte, [
                'email' => 'proctoring.employee@csc.gov.ph',
            ]))
            ->assertRedirect('/users?tab=staff');

        $member = Member::firstOrFail();
        $employee->refresh();

        $this->assertSame($employee->id, $member->user_id, 'Linked, not duplicated.');
        $this->assertSame(1, User::where('email', 'proctoring.employee@csc.gov.ph')->count());
        // The whole point: they stay staff and gain a second hat.
        $this->assertSame(UserRole::FoAdmin, $employee->role);
        $this->assertTrue(Workspace::availableTo($employee), 'The switcher now shows.');
    }

    /**
     * A Commission employee's employer is not in question, so the form arrives
     * with it filled in. Reports and payroll group by `agency`, and typing it by
     * hand each time is what produced several spellings of the one office.
     */
    public function test_registering_a_staff_account_prefills_the_commission_as_the_agency(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $employee = $this->staff(UserRole::FieldDirector, $this->leyte);

        $this->actingAs($admin)
            ->getJson("/members/create?user={$employee->id}")
            ->assertOk()
            ->assertJsonPath('account.agency', Member::CSC_AGENCY);

        // A member account is not staff, and their agency is their own employer
        // — there is nothing to suggest, so the field stays empty.
        $unlinked = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($admin)
            ->getJson("/members/create?user={$unlinked->id}")
            ->assertOk()
            ->assertJsonPath('account.agency', null);
    }

    /**
     * Staff entering a colleague's record often do not have their birth date to
     * hand; self-registration still requires it (see RegistrationTest), because
     * there the person filling the form is the person it describes.
     */
    public function test_staff_can_register_a_member_without_a_date_of_birth(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte, ['date_of_birth' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Member::firstOrFail()->date_of_birth);
    }

    /** The 18-year floor still applies to a date that *is* given. */
    public function test_a_supplied_date_of_birth_must_still_be_at_least_eighteen_years_ago(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte, [
                'date_of_birth' => now()->subYears(17)->toDateString(),
            ]))
            ->assertSessionHasErrors('date_of_birth');
    }

    /**
     * An employee's reach comes from their employment record — region-wide for
     * the regional office, their own office's centers for a field office — so
     * there is no one center to record and the field stops being required.
     */
    public function test_an_employee_may_be_registered_without_a_testing_center(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);
        $employee = User::factory()->create([
            'role' => UserRole::FoAdmin,
            'field_office_id' => $this->leyte->id,
            'email' => 'proctoring.employee@csc.gov.ph',
        ]);

        $this->actingAs($admin)
            ->getJson("/members/create?user={$employee->id}")
            ->assertJsonPath('account.is_employee', true);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte, [
                'email' => 'proctoring.employee@csc.gov.ph',
                'testing_center_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $member = Member::firstOrFail();
        $this->assertNull($member->testing_center_id);
        $this->assertSame($employee->id, $member->user_id);
    }

    /** External test administrators still have to be placed somewhere. */
    public function test_an_external_member_still_requires_a_testing_center(): void
    {
        $admin = $this->staff(UserRole::FoAdmin, $this->leyte);

        $this->actingAs($admin)
            ->post('/members', $this->memberPayload($this->leyte, ['testing_center_id' => null]))
            ->assertSessionHasErrors('testing_center_id');
    }

    /**
     * And the list stops asking staff to fix it — the amber flag is for records
     * the backfill could not place, not for employees who need no center.
     */
    public function test_an_employee_with_no_centre_is_not_flagged_as_needing_one(): void
    {
        $admin = $this->staff(UserRole::EsdAdmin, null);
        $employee = $this->staff(UserRole::FoAdmin, $this->leyte);
        Member::factory()->create(['user_id' => $employee->id, 'testing_center_id' => null]);

        $this->actingAs($admin)
            ->get('/members')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('members.data.0.needs_testing_center', false)
                ->etc());
    }

    /** An employee already carrying a center can have it taken back off. */
    public function test_an_employees_testing_center_can_be_cleared_on_edit(): void
    {
        $admin = $this->staff(UserRole::SuperAdmin, null);
        $employee = $this->staff(UserRole::FoAdmin, $this->leyte);
        $member = Member::factory()->create([
            'user_id' => $employee->id,
            'field_office_id' => $this->leyte->id,
            'testing_center_id' => $this->centerFor($this->leyte)->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/members/{$member->id}/edit-data")
            ->assertJsonPath('isEmployee', true);

        $this->actingAs($admin)
            ->put("/members/{$member->id}", [
                ...$this->memberPayload($this->leyte, [
                    'email' => $member->email,
                    'testing_center_id' => null,
                ]),
                'status' => $member->status->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($member->fresh()->testing_center_id);
    }

    /** Without the create ability there is no form to seed. */
    public function test_create_form_data_requires_the_manage_members_permission(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->getJson('/members/create')->assertForbidden();
    }
}
