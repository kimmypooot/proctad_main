<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
}
