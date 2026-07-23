<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ConfirmationAction;
use App\Enums\EligibilityRequirement;
use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyProctadTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_member_sees_profile_and_id_card(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/my/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/Profile')
                ->where('member.proctad_id', $member->proctad_id)
                ->has('requirements'));

        $this->actingAs($user)
            ->get('/my/qr-code')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/QrCode')
                ->where('idCard.proctad_id', $member->proctad_id)
                ->where('idCard.qr_value', route('verify', $member->proctad_id)));
    }

    public function test_unlinked_user_gets_empty_state(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($user)
            ->get('/my/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('member', null));

        $this->actingAs($user)
            ->get('/my/qr-code')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('idCard', null));
    }

    public function test_service_history_shows_only_own_records(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);
        $otherMember = Member::factory()->create(['field_office_id' => $office->id]);
        $exam = \App\Models\Examination::factory()->create();

        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $member->id,
            'field_office_id' => $office->id,
        ]);
        \App\Models\ExamAssignment::factory()->create([
            'examination_id' => $exam->id,
            'member_id' => $otherMember->id,
            'field_office_id' => $office->id,
        ]);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/ServiceHistory')
                ->where('hasRecord', true)
                ->has('records', 1)
                ->where('records.0.exam_title', $exam->title));
    }

    public function test_member_can_fetch_own_photo_but_not_others(): void
    {
        Storage::fake('local');

        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $path = UploadedFile::fake()->image('me.jpg')->store('member-photos', 'local');

        $own = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id, 'photo_path' => $path]);
        $other = Member::factory()->create(['field_office_id' => $office->id, 'photo_path' => $path]);

        $this->actingAs($user)->get("/members/{$own->id}/photo")->assertOk();
        $this->actingAs($user)->get("/members/{$other->id}/photo")->assertForbidden();
    }

    /**
     * Members learn their room only after reporting in and being scanned — never in
     * advance — and it stays visible on the record afterwards. The withheld value
     * must be absent from the payload, not merely hidden in the template.
     *
     * @return array{0: \App\Models\User, 1: \App\Models\ExamAssignment}
     */
    private function assignmentWithRoom(string $examDate, ?string $scannedAt): array
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office '.uniqid(), 'code' => strtoupper(uniqid())]);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);

        $examination = \App\Models\Examination::factory()->create(['exam_date' => $examDate]);
        $venue = \App\Models\ExaminationSchool::factory()->create(['examination_id' => $examination->id]);
        $room = \App\Models\ExamRoom::factory()->create([
            'examination_school_id' => $venue->id,
            'room_number' => 'Room-007',
            'designation' => null,
        ]);

        $assignment = \App\Models\ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'examination_id' => $examination->id,
            'examination_school_id' => $venue->id,
            'exam_room_id' => $room->id,
            'field_office_id' => $office->id,
            'attendance_confirmed_at' => $scannedAt,
        ]);

        return [$user, $assignment];
    }

    public function test_room_is_shown_on_exam_day_once_the_member_has_been_scanned_in(): void
    {
        [$user] = $this->assignmentWithRoom(now()->toDateString(), now()->toDateTimeString());

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.room', 'Room-007')
                ->where('records.0.room_withheld', false));
    }

    public function test_room_is_withheld_on_exam_day_until_the_member_is_scanned_in(): void
    {
        [$user] = $this->assignmentWithRoom(now()->toDateString(), null);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.room', null)
                ->where('records.0.room_withheld', true));
    }

    public function test_room_remains_visible_in_past_records_once_it_was_revealed(): void
    {
        [$user] = $this->assignmentWithRoom(now()->subMonth()->toDateString(), now()->subMonth()->toDateTimeString());

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.room', 'Room-007')
                ->where('records.0.room_withheld', false));
    }

    public function test_room_is_withheld_for_a_past_exam_the_member_never_reported_to(): void
    {
        [$user] = $this->assignmentWithRoom(now()->subMonth()->toDateString(), null);

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.room', null)
                ->where('records.0.room_withheld', true));
    }

    public function test_room_is_withheld_before_exam_day_even_if_attendance_exists(): void
    {
        [$user] = $this->assignmentWithRoom(now()->addWeek()->toDateString(), now()->toDateTimeString());

        $this->actingAs($user)
            ->get('/my/service-history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.room', null)
                ->where('records.0.room_withheld', true));
    }

    /**
     * A member with no stored requirement rows — as an ETL import may well be —
     * must still see the full list as outstanding. Mapping stored rows alone
     * showed them an empty list while staff saw everything as not complied.
     */
    public function test_requirements_list_is_complete_even_with_no_stored_rows(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $member->requirements()->delete();

        $this->actingAs($user)
            ->get('/my/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requirements', count(EligibilityRequirement::cases()))
                ->where('requirements.0.complied', false)
                ->where('requirements.0.label', EligibilityRequirement::cases()[0]->label()));
    }

    /**
     * A member may submit evidence but must never be able to mark themselves
     * eligible — the Field Office verifies and flips `complied`.
     */
    public function test_member_uploads_a_requirement_document_without_becoming_compliant(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $key = EligibilityRequirement::cases()[0];

        $this->actingAs($user)
            ->post("/my/requirements/{$key->value}", [
                'file' => UploadedFile::fake()->create('clearance.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $record = $member->requirements()->where('requirement', $key)->first();
        $this->assertNotNull($record->file_path);
        $this->assertFalse((bool) $record->complied);
        Storage::disk('local')->assertExists($record->file_path);
    }

    public function test_requirement_upload_rejects_unsupported_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/my/requirements/'.EligibilityRequirement::cases()[0]->value, [
                'file' => UploadedFile::fake()->create('payload.exe', 10),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_unknown_requirement_key_is_404(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/my/requirements/not-a-requirement', [
                'file' => UploadedFile::fake()->create('clearance.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    /** Replacing evidence a Field Office already accepted would erase the basis of that decision. */
    public function test_member_cannot_replace_an_already_verified_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $key = EligibilityRequirement::cases()[0];
        // The factory does not seed requirement rows, so create the verified one.
        $member->requirements()->create([
            'requirement' => $key,
            'complied' => true,
            'file_path' => 'kept.pdf',
        ]);

        $this->actingAs($user)
            ->post("/my/requirements/{$key->value}", [
                'file' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('kept.pdf', $member->requirements()->where('requirement', $key)->first()->file_path);
    }

    private function upcomingAssignmentFor(Member $member, string $status = 'pending'): ExamAssignment
    {
        return ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'field_office_id' => $member->field_office_id,
            'status' => $status,
            'confirmation_sent_at' => now(),
            'examination_id' => Examination::factory()->create(['exam_date' => now()->addDays(30)])->id,
        ]);
    }

    public function test_member_sees_only_upcoming_assignments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);

        $this->upcomingAssignmentFor($member);
        ExamAssignment::factory()->create([
            'member_id' => $member->id,
            'examination_id' => Examination::factory()->create(['exam_date' => now()->subDays(30)])->id,
        ]);

        $this->actingAs($user)
            ->get('/my/assignments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('My/Assignments')
                ->where('hasRecord', true)
                ->has('records', 1)
                ->where('records.0.awaiting_response', true));
    }

    /**
     * Room is disclosed in person on exam day. It must be absent from the
     * payload, not merely hidden in the template.
     */
    public function test_assignment_payload_never_carries_the_room(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $this->upcomingAssignmentFor($member);

        $this->actingAs($user)
            ->get('/my/assignments')
            ->assertInertia(fn (Assert $page) => $page->missing('records.0.room'));
    }

    public function test_member_can_confirm_their_own_assignment(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $assignment = $this->upcomingAssignmentFor($member);

        $this->actingAs($user)
            ->post("/my/assignments/{$assignment->id}/respond", ['action' => 'confirm'])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Confirmed, $assignment->status);
        $this->assertNotNull($assignment->responded_at);
        $this->assertSame(1, $assignment->confirmations()->where('action', ConfirmationAction::Confirmed)->count());
    }

    public function test_declining_requires_a_reason_and_records_it(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $assignment = $this->upcomingAssignmentFor($member);

        $this->actingAs($user)
            ->post("/my/assignments/{$assignment->id}/respond", ['action' => 'decline'])
            ->assertSessionHasErrors('decline_reason');

        $this->actingAs($user)
            ->post("/my/assignments/{$assignment->id}/respond", [
                'action' => 'decline',
                'decline_reason' => 'Hospital duty that weekend.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Declined, $assignment->status);
        $this->assertSame('Hospital duty that weekend.', $assignment->decline_reason);
    }

    /** The whole point of the authorization check: nobody answers for anybody else. */
    public function test_member_cannot_respond_to_someone_elses_assignment(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        Member::factory()->create(['user_id' => $user->id]);

        $someoneElse = Member::factory()->create();
        $theirAssignment = $this->upcomingAssignmentFor($someoneElse);

        $this->actingAs($user)
            ->post("/my/assignments/{$theirAssignment->id}/respond", ['action' => 'confirm'])
            ->assertForbidden();

        $this->assertSame(AssignmentStatus::Pending, $theirAssignment->fresh()->status);
    }

    /** Responses are one-shot, exactly as through the emailed link. */
    public function test_responding_twice_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['user_id' => $user->id]);
        $assignment = $this->upcomingAssignmentFor($member, 'confirmed');

        $this->actingAs($user)
            ->post("/my/assignments/{$assignment->id}/respond", [
                'action' => 'decline',
                'decline_reason' => 'Changed my mind.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(AssignmentStatus::Confirmed, $assignment->fresh()->status);
    }

    /** The member view and the staff view must agree on what is outstanding. */
    public function test_member_and_staff_requirement_lists_are_the_same_length(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $admin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $office->id]);
        $user = User::factory()->create(['role' => UserRole::Member]);
        $member = Member::factory()->create(['field_office_id' => $office->id, 'user_id' => $user->id]);
        $member->requirements()->delete();

        $staffCount = count($this->actingAs($admin)
            ->getJson("/members/{$member->id}/details")
            ->json('requirements'));

        $this->actingAs($user)
            ->get('/my/profile')
            ->assertInertia(fn (Assert $page) => $page->has('requirements', $staffCount));
    }
}
