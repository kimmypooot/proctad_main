<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\Permission;
use App\Enums\PersonnelType;
use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\DesignationCategory;
use App\Models\ExamAssignment;
use App\Models\FeeSchedule;
use App\Models\User;
use App\Services\RoomStaffingCalculator;
use App\Support\DesignationRegistry;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DesignationRegistry::flush();
        PermissionRegistry::flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::EsdAdmin]);
    }

    private function designation(string $key, PayeeType $type = PayeeType::ExamRole): Designation
    {
        return Designation::where('section', $type->value)->where('key', $key)->firstOrFail();
    }

    private function category(string $key, PayeeType $type = PayeeType::ExamRole): DesignationCategory
    {
        return DesignationCategory::where('section', $type->value)->where('key', $key)->firstOrFail();
    }

    private function saveVia(User $actor, Designation $designation, array $overrides = [])
    {
        return $this->actingAs($actor)->put("/designations/{$designation->id}", [
            'label' => $designation->label,
            'designation_category_id' => $designation->designation_category_id,
            'is_active' => true,
            'amount' => 0,
            ...$overrides,
        ]);
    }

    public function test_the_builtins_are_seeded_from_the_enums(): void
    {
        $this->assertCount(count(ExamRole::cases()), Designation::section(PayeeType::ExamRole->value)->get());
        $this->assertCount(count(PersonnelType::cases()), Designation::section(PayeeType::PersonnelType->value)->get());
        $this->assertSame('Room Examiner', ExamRole::RoomExaminer->label());
        $this->assertTrue($this->designation('proctor')->is_builtin);
    }

    public function test_renaming_a_designation_reaches_every_caller(): void
    {
        $this->saveVia($this->admin(), $this->designation('proctor'), ['label' => 'Room Proctor'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        DesignationRegistry::flush();

        $this->assertSame('Room Proctor', ExamRole::Proctor->label());
        // The stored key is untouched, so history and payroll still match.
        $this->assertSame('proctor', $this->designation('proctor')->key);
    }

    /** The rate written here is the one the payroll reports read. */
    public function test_the_rate_is_stored_against_the_designation(): void
    {
        $admin = $this->admin();

        $this->saveVia($admin, $this->designation('driver'), ['amount' => 1500.50])->assertRedirect();

        $this->assertSame(150050, FeeSchedule::rateForRole(ExamRole::Driver)->amount_cents);

        $this->actingAs($admin)->get('/designations')->assertInertia(
            fn (Assert $page) => $page->where(
                'sections',
                fn ($sections) => $this->findDesignation($sections, 'driver')['amount'] === 1500.5,
            ),
        );
    }

    public function test_deactivating_keeps_the_rate_for_when_it_returns(): void
    {
        $admin = $this->admin();

        $this->saveVia($admin, $this->designation('driver'), ['is_active' => false, 'amount' => 750])
            ->assertRedirect();

        DesignationRegistry::flush();

        $this->assertFalse(DesignationRegistry::isActive(PayeeType::ExamRole, 'driver'));
        // Dropped from assignment lists, but the rate survives so switching it
        // back on restores what it paid rather than zeroing it.
        $this->assertNotContains('driver', DesignationRegistry::activeKeys(PayeeType::ExamRole));
        $this->assertSame(75000, FeeSchedule::rateForRole(ExamRole::Driver)->amount_cents);
    }

    /**
     * A rate is money, gated separately from naming and filing designations —
     * holding designations.manage alone must not be a way to change what a duty
     * pays.
     */
    public function test_setting_a_rate_requires_the_fee_permission(): void
    {
        $admin = $this->admin();
        $driver = $this->designation('driver');

        $this->saveVia($admin, $driver, ['amount' => 900])->assertRedirect();
        $this->assertSame(90000, FeeSchedule::rateForRole(ExamRole::Driver)->amount_cents);

        DB::table('role_permissions')->updateOrInsert(
            ['role' => UserRole::EsdAdmin->value, 'permission' => Permission::FeeSchedulesManage->value],
            ['granted' => false, 'created_at' => now(), 'updated_at' => now()],
        );
        PermissionRegistry::flush();

        // The rename still lands; the rate is left exactly as it was.
        $this->saveVia($admin->fresh(), $driver, ['label' => 'Vehicle Driver', 'amount' => 5000])
            ->assertRedirect();

        DesignationRegistry::flush();

        $this->assertSame('Vehicle Driver', $driver->fresh()->label);
        $this->assertSame(90000, FeeSchedule::rateForRole(ExamRole::Driver)->amount_cents);
    }

    /** @param  iterable<int, mixed>  $sections */
    private function findDesignation(iterable $sections, string $key): array
    {
        return collect($sections)
            ->flatMap(fn ($section) => collect($section['categories'])->flatMap(fn ($c) => $c['designations']))
            ->firstWhere('key', $key);
    }

    /* --- Adding --- */

    public function test_a_custom_designation_can_be_added_and_is_assignable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('school')->id,
            'label' => 'Room Marshal',
            'amount' => 425,
        ])->assertRedirect()->assertSessionHasNoErrors();

        DesignationRegistry::flush();

        $custom = $this->designation('room_marshal');
        $this->assertFalse($custom->is_builtin);
        $this->assertSame('Room Marshal', $custom->label);
        $this->assertSame(42500, FeeSchedule::where('payee_value', 'room_marshal')->first()->amount_cents);
        $this->assertContains('room_marshal', DesignationRegistry::activeKeys(PayeeType::ExamRole));
    }

    /**
     * The reason designations had to leave the enum: an assignment carrying a
     * custom designation has to load without throwing.
     */
    public function test_an_assignment_can_carry_a_custom_designation(): void
    {
        $this->actingAs($this->admin())->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('school')->id,
            'label' => 'Room Marshal',
            'amount' => 0,
        ])->assertRedirect();

        DesignationRegistry::flush();

        $assignment = ExamAssignment::factory()->create(['role' => 'room_marshal']);
        $assignment->refresh();

        $this->assertSame('room_marshal', $assignment->role->value);
        $this->assertSame('Room Marshal', $assignment->role->label());
        $this->assertTrue($assignment->role->isCustom());
        // Structural rules do not reach a designation the system has never heard of.
        $this->assertFalse($assignment->role->isEvaluable());
        $this->assertFalse($assignment->role->isCoverable());
    }

    public function test_a_committee_from_the_other_list_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('support', PayeeType::PersonnelType)->id,
            'label' => 'Mismatched',
            'amount' => 0,
        ])->assertStatus(422);
    }

    /* --- Moving --- */

    public function test_moving_a_designation_between_committees_changes_its_coverage(): void
    {
        $driver = $this->designation('driver');

        // Special roles are not coverage duties; REC members are.
        $this->assertFalse(ExamRole::Driver->isCoverageRole());

        $this->saveVia($this->admin(), $driver, [
            'designation_category_id' => $this->category('regional')->id,
        ])->assertRedirect();

        DesignationRegistry::flush();

        $this->assertSame('regional', $driver->fresh()->category->key);

        $assignment = ExamAssignment::factory()->create(['role' => 'driver']);
        $this->assertTrue($assignment->refresh()->role->isCoverageRole());
    }

    /* --- Room grid --- */

    /**
     * The grid's columns are data: a custom designation given a rooms-covered
     * value is staffed there alongside the built-in three.
     */
    public function test_a_custom_designation_with_a_room_slot_joins_the_room_grid(): void
    {
        $this->assertSame(
            ['proctor', 'room_examiner', 'supervising_examiner'],
            array_column(DesignationRegistry::roomDesignations(), 'key'),
        );

        $this->actingAs($this->admin())->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('school')->id,
            'label' => 'Room Marshal',
            'amount' => 0,
            'rooms_per_slot' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        DesignationRegistry::flush();

        $this->assertContains('room_marshal', array_column(DesignationRegistry::roomDesignations(), 'key'));

        // And the calculator asks for one per room, like a Proctor.
        $rooms = collect(range(1, 3))->map(fn (int $i) => (object) [
            'id' => $i, 'room_number' => "Room-{$i}", 'capacity' => 25, 'designation' => null,
        ]);

        $stats = app(RoomStaffingCalculator::class)->stats($rooms, collect());

        $this->assertSame(3, $stats['required']['room_marshal']);
        $this->assertSame(3, $stats['required']['proctor']);
        // One supervisor still covers all three rooms.
        $this->assertSame(1, $stats['required']['supervising_examiner']);
    }

    public function test_a_designation_without_a_room_slot_stays_out_of_the_grid(): void
    {
        $this->actingAs($this->admin())->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('special')->id,
            'label' => 'Liaison Officer',
            'amount' => 0,
        ])->assertRedirect();

        DesignationRegistry::flush();

        $this->assertNotContains('liaison_officer', array_column(DesignationRegistry::roomDesignations(), 'key'));
    }

    /* --- Deleting --- */

    public function test_a_builtin_designation_cannot_be_deleted(): void
    {
        $proctor = $this->designation('proctor');

        $this->actingAs($this->admin())->delete("/designations/{$proctor->id}", [
            'confirm_label' => $proctor->label,
        ])->assertStatus(422);

        $this->assertDatabaseHas('designations', ['key' => 'proctor']);
    }

    public function test_deleting_requires_the_name_to_be_retyped(): void
    {
        $custom = $this->makeCustom();

        $this->actingAs($this->admin())->delete("/designations/{$custom->id}", [
            'confirm_label' => 'Wrong Name',
        ])->assertSessionHasErrors('confirm_label');

        $this->assertDatabaseHas('designations', ['key' => $custom->key]);
    }

    public function test_a_designation_in_use_cannot_be_deleted(): void
    {
        $custom = $this->makeCustom();
        ExamAssignment::factory()->create(['role' => $custom->key]);

        $this->actingAs($this->admin())->delete("/designations/{$custom->id}", [
            'confirm_label' => $custom->label,
        ])->assertStatus(422);

        $this->assertDatabaseHas('designations', ['key' => $custom->key]);
    }

    public function test_an_unused_custom_designation_can_be_deleted(): void
    {
        $custom = $this->makeCustom();

        $this->actingAs($this->admin())->delete("/designations/{$custom->id}", [
            'confirm_label' => $custom->label,
        ])->assertRedirect();

        $this->assertDatabaseMissing('designations', ['key' => $custom->key]);
        // The orphaned rate goes with it.
        $this->assertDatabaseMissing('fee_schedules', ['payee_value' => $custom->key]);
    }

    /* --- Committees --- */

    public function test_a_committee_can_be_added_and_renamed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/designation-categories', [
            'section' => PayeeType::ExamRole->value,
            'label' => 'Mobile Teams',
        ])->assertRedirect();

        $category = $this->category('mobile_teams');
        $this->assertFalse($category->is_builtin);

        $this->actingAs($admin)->put("/designation-categories/{$category->id}", [
            'label' => 'Mobile Inspection Teams',
        ])->assertRedirect();

        $this->assertSame('Mobile Inspection Teams', $category->fresh()->label);
    }

    public function test_a_builtin_committee_cannot_be_deleted(): void
    {
        $regional = $this->category('regional');

        $this->actingAs($this->admin())->delete("/designation-categories/{$regional->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('designation_categories', ['key' => 'regional']);
    }

    public function test_a_committee_holding_designations_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/designation-categories', [
            'section' => PayeeType::ExamRole->value,
            'label' => 'Mobile Teams',
        ])->assertRedirect();

        $category = $this->category('mobile_teams');

        $this->actingAs($admin)->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $category->id,
            'label' => 'Team Lead',
            'amount' => 0,
        ])->assertRedirect();

        $this->actingAs($admin)->delete("/designation-categories/{$category->id}")->assertStatus(422);

        $this->assertDatabaseHas('designation_categories', ['key' => 'mobile_teams']);
    }

    /* --- Access --- */

    public function test_only_holders_of_the_permission_reach_the_page(): void
    {
        $this->actingAs($this->admin())->get('/designations')->assertOk();

        foreach ([UserRole::FoAdmin, UserRole::FieldDirector, UserRole::DirectorIv] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/designations')->assertForbidden();
        }
    }

    public function test_the_permission_can_narrow_access(): void
    {
        $esdAdmin = $this->admin();

        DB::table('role_permissions')->updateOrInsert(
            ['role' => UserRole::EsdAdmin->value, 'permission' => Permission::DesignationsManage->value],
            ['granted' => false, 'created_at' => now(), 'updated_at' => now()],
        );
        PermissionRegistry::flush();

        $this->actingAs($esdAdmin->fresh())->get('/designations')->assertForbidden();
    }

    private function makeCustom(): Designation
    {
        $this->actingAs($this->admin())->post('/designations', [
            'section' => PayeeType::ExamRole->value,
            'designation_category_id' => $this->category('school')->id,
            'label' => 'Room Marshal',
            'amount' => 100,
        ])->assertRedirect();

        DesignationRegistry::flush();

        return $this->designation('room_marshal');
    }
}
