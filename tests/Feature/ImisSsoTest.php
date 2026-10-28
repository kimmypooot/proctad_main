<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FieldOffice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ImisSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.imis.sso_url' => 'https://imis.test/api/v1/sso/validate', 'services.imis.audience' => 'proctad.test']);
    }

    /** @var array<string, mixed> */
    private array $imisAnswer = [];

    /** What IMIS answers for a person, overridable per test. */
    private function imis(array $user = []): void
    {
        $this->imisAnswer = ['success' => true, 'user' => $user + [
            'id' => 7, 'username' => 'staff1', 'fname' => 'Juan', 'lname' => 'Cruz', 'mname' => '', 'email' => 'juan@csc.test',
            'role' => 'Administrator (ESD)', 'role_code' => 'esd_admin', 'office_code' => 'ESD', 'office_type' => 'regional_support_unit',
        ]];

        // Registered once; the answer is read when the request is made.
        Http::fake(['imis.test/*' => fn () => Http::response($this->imisAnswer)]);
    }

    private function regionalOffice(): FieldOffice
    {
        return FieldOffice::factory()->create(['is_regional' => true]);
    }

    public function test_the_link_from_imis_shows_the_splash_with_the_token(): void
    {
        $this->get('/sso?sso_token=abc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ImisSso')->where('token', 'abc'));
        $this->assertGuest();
    }

    public function test_a_staff_stub_is_created_on_first_sign_in_with_the_imis_role_and_office(): void
    {
        $regional = $this->regionalOffice();
        $this->imis();

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $user = User::where('imis_user_id', 7)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::EsdAdmin, $user->role);
        $this->assertSame('Juan Cruz', $user->name);
        $this->assertSame($regional->id, $user->field_office_id);
        $this->assertTrue($user->is_active);
        Http::assertSent(fn ($r) => $r['token'] === 'abc' && $r['audience'] === 'proctad.test');
        $this->assertTrue(AuditLog::where('user_id', $user->id)->where('action', 'login')->exists());
    }

    public function test_the_imis_photo_is_shown_as_the_avatar_and_kept_when_imis_has_none(): void
    {
        $this->regionalOffice();
        $this->imis(['photo_url' => 'https://imis.test/storage/profile-photos/a.jpg']);
        $this->post('/sso', ['sso_token' => 'abc']);

        $user = User::where('imis_user_id', 7)->firstOrFail();
        $this->assertSame('https://imis.test/storage/profile-photos/a.jpg', $user->google_avatar);

        $this->post('/logout');
        $this->imis(['photo_url' => null]);
        $this->post('/sso', ['sso_token' => 'def']);
        $this->assertSame('https://imis.test/storage/profile-photos/a.jpg', $user->fresh()->google_avatar);

        $this->post('/logout');
        $this->imis(['photo_url' => 'javascript:alert(1)']);
        $this->post('/sso', ['sso_token' => 'ghi']);
        $this->assertSame('https://imis.test/storage/profile-photos/a.jpg', $user->fresh()->google_avatar);
    }

    public function test_a_field_office_role_gets_the_field_office_mapped_from_the_imis_unit(): void
    {
        $this->regionalOffice();
        $office = FieldOffice::factory()->create(['is_regional' => false, 'imis_code' => 'LFO-I']);
        $this->imis(['role_code' => 'fo_admin', 'office_code' => 'LFO-I', 'office_type' => 'field_office']);

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $this->assertSame($office->id, User::where('imis_user_id', 7)->value('field_office_id'));
    }

    public function test_a_field_office_role_without_a_matching_field_office_is_refused(): void
    {
        $this->regionalOffice();
        $this->imis(['role_code' => 'fo_admin', 'office_code' => 'NOPE', 'office_type' => 'field_office']);

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::where('imis_user_id', 7)->count());
    }

    public function test_role_and_office_are_rewritten_from_imis_on_every_sign_in(): void
    {
        $regional = $this->regionalOffice();
        $office = FieldOffice::factory()->create(['is_regional' => false, 'imis_code' => 'SFO']);
        $this->imis();
        $this->post('/sso', ['sso_token' => 'abc']);
        $this->post('/logout');
        $this->assertGuest();

        $this->imis(['role_code' => 'fo_admin', 'office_code' => 'SFO', 'office_type' => 'field_office']);
        $this->post('/sso', ['sso_token' => 'def'])->assertRedirect(route('dashboard'));

        $user = User::where('imis_user_id', 7)->firstOrFail();
        $this->assertSame(UserRole::FoAdmin, $user->role);
        $this->assertSame($office->id, $user->field_office_id);
        $this->assertSame(1, User::where('imis_user_id', 7)->count());
        $this->assertNotSame($regional->id, $user->field_office_id);
    }

    public function test_an_existing_staff_account_is_linked_by_username(): void
    {
        $this->regionalOffice();
        $existing = User::factory()->create(['username' => 'staff1', 'role' => UserRole::SuperAdmin]);
        $this->imis();

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(7, $existing->fresh()->imis_user_id);
        $this->assertSame(UserRole::EsdAdmin, $existing->fresh()->role);
    }

    public function test_a_matching_member_account_is_never_promoted(): void
    {
        $this->regionalOffice();
        $member = User::factory()->create(['email' => 'juan@csc.test', 'role' => UserRole::Member]);
        $this->imis();

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(UserRole::Member, $member->fresh()->role);
        $this->assertNull($member->fresh()->imis_user_id);
    }

    public function test_no_role_unknown_role_or_invalid_token_is_refused(): void
    {
        $this->regionalOffice();

        $this->post('/sso')->assertRedirect(route('login'));
        $this->get('/sso')->assertRedirect(route('login'));

        foreach ([['role_code' => null], ['role_code' => 'made_up']] as $override) {
            $this->imis($override);
            $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('login'));
            $this->assertGuest();
        }

        $this->imisAnswer = ['success' => false];
        $this->post('/sso', ['sso_token' => 'bad'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, User::where('imis_user_id', 7)->count());
    }

    public function test_a_regional_employee_granted_member_in_imis_signs_in_as_a_member(): void
    {
        $regional = $this->regionalOffice();
        $this->imis(['role_code' => 'member']);

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $user = User::where('imis_user_id', 7)->firstOrFail();
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame($regional->id, $user->field_office_id);
    }

    public function test_an_existing_member_account_is_linked_by_email_when_imis_grants_member(): void
    {
        $this->regionalOffice();
        $member = User::factory()->create(['email' => 'juan@csc.test', 'role' => UserRole::Member, 'google_id' => 'g-1']);
        $this->imis(['role_code' => 'member']);

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($member);
        $this->assertSame(7, $member->fresh()->imis_user_id);
        $this->assertSame(UserRole::Member, $member->fresh()->role);
    }

    public function test_a_member_linked_to_imis_cannot_use_a_password_but_an_external_member_can(): void
    {
        $linked = User::factory()->create(['role' => UserRole::Member, 'imis_user_id' => 9]);
        $this->post('/login', ['login' => $linked->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $external = User::factory()->create(['role' => UserRole::Member]);
        $this->post('/login', ['login' => $external->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($external);
    }

    public function test_an_inactive_stub_is_refused(): void
    {
        $this->regionalOffice();
        User::factory()->create(['imis_user_id' => 7, 'role' => UserRole::EsdAdmin, 'is_active' => false]);
        $this->imis();

        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_staff_cannot_use_a_password_but_a_super_admin_and_members_can(): void
    {
        $staff = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $this->post('/login', ['login' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->post('/login', ['login' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        auth()->logout();

        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->post('/login', ['login' => $member->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($member);
    }

    public function test_staff_password_login_is_unchanged_while_imis_sign_in_is_off(): void
    {
        config(['services.imis.sso_url' => '']);
        $staff = User::factory()->create(['role' => UserRole::EsdAdmin]);

        $this->post('/login', ['login' => $staff->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_imis_signing_out_ends_the_proctad_session_it_started(): void
    {
        $this->regionalOffice();
        $sid = str_repeat('s', 64);
        $this->imis(['sid' => $sid]);
        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $row = DB::table('imis_sessions')->sole();
        $this->assertSame(hash('sha256', $sid), $row->sid_hash);
        Session::getHandler()->write($row->session_id, 'payload');

        $this->postJson('/sso/logout', ['sid' => $sid])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, DB::table('imis_sessions')->count());
        $this->assertSame('', Session::getHandler()->read($row->session_id));
    }

    public function test_an_unknown_sid_signs_nobody_out_and_is_not_an_error(): void
    {
        $this->regionalOffice();
        $this->imis(['sid' => str_repeat('s', 64)]);
        $this->post('/sso', ['sso_token' => 'abc']);

        $this->postJson('/sso/logout', ['sid' => str_repeat('x', 64)])->assertOk();
        $this->postJson('/sso/logout', ['sid' => 'short'])->assertOk();
        $this->postJson('/sso/logout')->assertOk();

        $this->assertSame(1, DB::table('imis_sessions')->count());
    }

    public function test_the_logout_call_works_during_maintenance(): void
    {
        Setting::set('site_maintenance_mode', true, 'boolean');

        $this->postJson('/sso/logout', ['sid' => str_repeat('x', 64)])->assertOk();
    }

    public function test_no_sid_from_imis_records_nothing(): void
    {
        $this->regionalOffice();
        $this->imis();
        $this->post('/sso', ['sso_token' => 'abc'])->assertRedirect(route('dashboard'));

        $this->assertSame(0, DB::table('imis_sessions')->count());
    }
}
