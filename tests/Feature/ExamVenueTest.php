<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\FieldOffice;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamVenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_attach_a_school_as_a_venue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $school = School::factory()->create();

        $this->actingAs($admin)
            ->post("/examinations/{$examination->id}/venues", ['school_id' => $school->id])
            ->assertRedirect();

        $this->assertSame(1, ExaminationSchool::where('examination_id', $examination->id)
            ->where('school_id', $school->id)->count());
    }

    public function test_the_same_school_cannot_be_attached_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $examination = Examination::factory()->create();
        $school = School::factory()->create();
        ExaminationSchool::create(['examination_id' => $examination->id, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->post("/examinations/{$examination->id}/venues", ['school_id' => $school->id])
            ->assertSessionHasErrors('school_id');
    }

    public function test_fo_admin_cannot_attach_a_school_from_another_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $examination = Examination::factory()->create();
        $school = School::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)
            ->post("/examinations/{$examination->id}/venues", ['school_id' => $school->id])
            ->assertForbidden();
    }

    public function test_removing_a_venue_cascades_its_rooms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();
        $room = ExamRoom::factory()->create(['examination_school_id' => $venue->id]);

        $this->actingAs($admin)->delete("/venues/{$venue->id}")->assertRedirect();

        $this->assertModelMissing($venue);
        $this->assertModelMissing($room);
    }

    public function test_admin_can_add_and_remove_a_room(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $venue = ExaminationSchool::factory()->create();

        $this->actingAs($admin)
            ->post("/venues/{$venue->id}/rooms", ['room_number' => 'Room-001', 'capacity' => 30])
            ->assertRedirect();

        $room = ExamRoom::firstOrFail();
        $this->assertSame('Room-001', $room->room_number);

        $this->actingAs($admin)->delete("/exam-rooms/{$room->id}")->assertRedirect();
        $this->assertModelMissing($room);
    }

    public function test_fo_admin_cannot_add_room_to_another_offices_venue(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->create(['field_office_id' => $otherFo->id]);
        $venue = ExaminationSchool::factory()->create(['school_id' => $school->id]);

        $this->actingAs($foAdmin)
            ->post("/venues/{$venue->id}/rooms", ['room_number' => 'Room-001', 'capacity' => 30])
            ->assertForbidden();
    }
}
