<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Letterhead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LetterheadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_super_admin_can_upload_and_activate_a_letterhead(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->post('/letterheads', [
            'name' => 'Official Letterhead 2026',
            'file' => UploadedFile::fake()->image('letterhead.png', 1240, 1754),
            'activate' => true,
        ])->assertRedirect();

        $letterhead = Letterhead::firstOrFail();
        $this->assertSame('Official Letterhead 2026', $letterhead->name);
        $this->assertTrue($letterhead->is_active);
        Storage::disk('local')->assertExists($letterhead->file_path);
    }

    public function test_activating_one_letterhead_deactivates_others(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $first = Letterhead::factory()->create(['is_active' => true]);
        $second = Letterhead::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post("/letterheads/{$second->id}/activate")->assertRedirect();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_fo_admin_cannot_manage_letterheads(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);

        $this->actingAs($foAdmin)->get('/letterheads')->assertForbidden();
        $this->actingAs($foAdmin)->post('/letterheads', [
            'name' => 'Nope',
            'file' => UploadedFile::fake()->image('letterhead.png'),
        ])->assertForbidden();
    }

    public function test_deleting_a_letterhead_removes_its_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $letterhead = Letterhead::factory()->create(['file_path' => 'letterheads/sample.png']);
        Storage::disk('local')->put($letterhead->file_path, 'fake-image-bytes');

        $this->actingAs($admin)->delete("/letterheads/{$letterhead->id}")->assertRedirect();

        $this->assertModelMissing($letterhead);
        Storage::disk('local')->assertMissing($letterhead->file_path);
    }

    public function test_preview_streams_the_file_for_authorized_staff(): void
    {
        $admin = User::factory()->create(['role' => UserRole::EsdAdmin]);
        $letterhead = Letterhead::factory()->create(['file_path' => 'letterheads/sample.png']);
        Storage::disk('local')->put($letterhead->file_path, 'fake-image-bytes');

        $this->actingAs($admin)->get("/letterheads/{$letterhead->id}/preview")->assertOk();
    }
}
