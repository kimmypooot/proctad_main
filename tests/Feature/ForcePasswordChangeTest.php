<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        return $user;
    }

    public function test_flagged_user_is_redirected_to_change_password(): void
    {
        $this->actingAs($this->flaggedUser())
            ->get('/dashboard')
            ->assertRedirect(route('password.edit'));
    }

    public function test_flagged_user_can_reach_change_password_page_and_logout(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->get('/change-password')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
    }

    public function test_updating_password_clears_flag_and_unblocks(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->put('/change-password', [
            'current_password' => 'password',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('brandnew123', $user->password));
        $this->assertTrue(
            AuditLog::where('user_id', $user->id)->where('action', 'password_changed')->exists(),
        );

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->put('/change-password', [
            'current_password' => 'not-the-password',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->put('/change-password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_unflagged_user_is_not_redirected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
