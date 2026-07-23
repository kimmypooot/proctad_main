<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function template(): EmailTemplate
    {
        return EmailTemplate::create([
            'code' => 'assignment_confirmation',
            'name' => 'Assignment Confirmation',
            'subject' => 'Confirm: {exam_name}',
            'body_html' => '<p>Hi {member_name}</p>',
            'variables' => ['member_name' => 'Full name', 'exam_name' => 'Exam name'],
            'is_active' => true,
        ]);
    }

    public function test_index_is_restricted_to_super_admin_and_esd_admin(): void
    {
        $this->template();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)
            ->get('/email-templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/EmailTemplates/Index')
                ->has('templates', 1));

        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $this->actingAs($foAdmin)->get('/email-templates')->assertForbidden();

        $management = User::factory()->create(['role' => UserRole::DirectorIv]);
        $this->actingAs($management)->get('/email-templates')->assertForbidden();
    }

    public function test_super_admin_can_edit_a_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $template = $this->template();

        $this->actingAs($admin)->put("/email-templates/{$template->id}", [
            'name' => 'Assignment Confirmation (Updated)',
            'subject' => 'Please confirm: {exam_name}',
            'body_html' => '<p>Dear {member_name}, please confirm.</p>',
            'body_plain' => 'Dear {member_name}, please confirm.',
            'is_active' => true,
        ])->assertRedirect();

        $template->refresh();
        $this->assertSame('Please confirm: {exam_name}', $template->subject);
        $this->assertSame('Assignment Confirmation (Updated)', $template->name);
    }

    public function test_edited_template_is_used_by_the_render_helper(): void
    {
        $template = $this->template();
        $template->update(['subject' => 'Updated Subject for {exam_name}']);

        $rendered = $template->render(['exam_name' => 'CSE-PPT', 'member_name' => 'Juan']);

        $this->assertSame('Updated Subject for CSE-PPT', $rendered['subject']);
    }

    public function test_esd_admin_cannot_disable_via_unauthorized_role(): void
    {
        $foAdmin = User::factory()->create(['role' => UserRole::FoAdmin]);
        $template = $this->template();

        $this->actingAs($foAdmin)->put("/email-templates/{$template->id}", [
            'name' => 'Hacked',
            'subject' => 'x',
            'body_html' => 'x',
            'is_active' => false,
        ])->assertForbidden();

        $this->assertTrue($template->fresh()->is_active);
    }
}
