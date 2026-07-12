<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'kmbanoyo']);

        $this->post('/login', ['login' => 'kmbanoyo', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_audited(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'login')->exists(),
        );
    }

    public function test_wrong_password_fails_and_increments_counter(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['login' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->failed_login_attempts);
        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'login_failed')->exists(),
        );
    }

    public function test_account_locks_after_five_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['login' => $user->email, 'password' => 'wrong']);
        }

        $this->assertNotNull($user->fresh()->locked_until);
        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'account_locked')->exists(),
        );
    }

    public function test_locked_account_rejects_correct_password(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_lock_expires_and_login_succeeds(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locked_until' => now()->subMinute()])->save();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'logout')->exists(),
        );
    }
}
