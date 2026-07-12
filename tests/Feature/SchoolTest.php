<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\School;
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

        $this->actingAs($admin)->post('/schools', [
            'name' => 'Leyte National High School',
            'municipality' => 'Tacloban City',
            'field_office_id' => $fo->id,
            'is_active' => true,
        ])->assertRedirect();

        $school = School::firstOrFail();
        $this->assertSame('Leyte National High School', $school->name);

        $this->actingAs($admin)->put("/schools/{$school->id}", [
            'name' => 'Leyte NHS',
            'municipality' => 'Tacloban City',
            'field_office_id' => $fo->id,
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

        $this->actingAs($foAdmin)->post('/schools', [
            'name' => 'Some School',
            'municipality' => 'Somewhere',
            'field_office_id' => $otherFo->id,
            'is_active' => true,
        ])->assertSessionHasErrors('field_office_id');

        $this->assertSame(0, School::count());
    }

    public function test_fo_admin_cannot_update_another_offices_school(): void
    {
        $fo = FieldOffice::factory()->create();
        $otherFo = FieldOffice::factory()->create();
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $fo->id]);
        $school = School::factory()->create(['field_office_id' => $otherFo->id]);

        $this->actingAs($foAdmin)->put("/schools/{$school->id}", [
            'name' => 'Hijacked',
            'municipality' => 'Nope',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_index_exposes_stats_and_venue_usage_count(): void
    {
        $fo = FieldOffice::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $used = School::factory()->create(['name' => 'AAA First School', 'field_office_id' => $fo->id, 'is_active' => true]);
        School::factory()->create(['name' => 'BBB Second School', 'field_office_id' => $fo->id, 'is_active' => true]);
        School::factory()->create(['name' => 'CCC Third School', 'field_office_id' => $fo->id, 'is_active' => false]);

        ExaminationSchool::factory()->count(2)->create(['school_id' => $used->id]);

        $this->actingAs($admin)
            ->get('/schools')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)
                ->where('stats.active', 2)
                ->where('stats.inactive', 1)
                ->where('schools.0.venues_count', 2)
            );
    }

    public function test_member_cannot_view_schools(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/schools')->assertForbidden();
    }
}
