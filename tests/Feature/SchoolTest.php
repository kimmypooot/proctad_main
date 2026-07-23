<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\School;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_any_school(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $fo = FieldOffice::factory()->create();
        $center = TestingCenter::factory()->forFieldOffice($fo)->create();

        $this->actingAs($admin)->post('/schools', [
            'name' => 'Leyte National High School',
            'testing_center_id' => $center->id,
            'is_active' => true,
        ])->assertRedirect();

        $school = School::firstOrFail();
        $this->assertSame('Leyte National High School', $school->name);
        $this->assertSame($center->id, $school->testing_center_id);
        // The school is reachable by the field office that handles its center.
        $this->assertTrue($school->handledByOffice($fo->id));

        $this->actingAs($admin)->put("/schools/{$school->id}", [
            'name' => 'Leyte NHS',
            'testing_center_id' => $center->id,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('Leyte NHS', $school->fresh()->name);

        $this->actingAs($admin)->delete("/schools/{$school->id}")->assertRedirect();
        $this->assertModelMissing($school);
    }

    public function test_fo_admin_cannot_create_school_outside_own_field_office(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $otherCenter = TestingCenter::factory()->forFieldOffice($otherFo)->create();

        $this->actingAs($foAdmin)->post('/schools', [
            'name' => 'Some School',
            'testing_center_id' => $otherCenter->id,
            'is_active' => true,
        ])->assertSessionHasErrors('testing_center_id');

        $this->assertSame(0, School::count());
    }

    public function test_fo_admin_cannot_update_another_offices_school(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->forFieldOffice($otherFo->id)->create();

        $this->actingAs($foAdmin)->put("/schools/{$school->id}", [
            'name' => 'Hijacked',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_locations_exposes_schools_with_venue_usage_count(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $used = School::factory()->forFieldOffice($fo->id)->create(['name' => 'AAA First School', 'is_active' => true]);
        School::factory()->forFieldOffice($fo->id)->create(['name' => 'BBB Second School', 'is_active' => true]);
        School::factory()->forFieldOffice($fo->id)->create(['name' => 'CCC Third School', 'is_active' => false]);

        ExaminationSchool::factory()->count(2)->create(['school_id' => $used->id]);

        $this->actingAs($admin)
            ->get('/locations')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Locations/Index')
                ->has('schools', 3)
                ->where('schools.0.name', 'AAA First School')
                ->where('schools.0.venues_count', 2)
            );
    }

    public function test_member_cannot_view_locations(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/locations')->assertForbidden();
    }
}
