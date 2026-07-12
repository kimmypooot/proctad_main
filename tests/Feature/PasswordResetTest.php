<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_renders(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_reset_link_is_sent_to_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_same_response_without_notification(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@nowhere.test'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_renders(): void
    {
        $this->get('/reset-password/some-token?email=a@b.test')->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true, 'locked_until' => now()->addHour()])->save();

        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->locked_until);
        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'password_reset')->exists(),
        );
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original1')]);

        $this->post('/reset-password', [
            'token' => 'bogus-token',
            'email' => $user->email,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original1', $user->fresh()->password));
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
