<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Baseline templates for the assignment confirmation workflow (spec / D5).
 * Ported from the legacy proctad_email_templates rows so fresh installs
 * (and installs run before the legacy ETL) still have working templates.
 * The legacy ETL imports the real, field-office-customized copies of these
 * over these seeds when it runs.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $variables = [
            'member_name' => 'Full name of the PROCTAD member',
            'exam_name' => 'Name of the examination',
            'exam_date' => 'Formatted examination date',
            'designation' => 'Role assigned',
            'proctad_id' => 'Member ID',
            'confirmation_url' => 'Unique confirmation link',
        ];

        EmailTemplate::query()->firstOrCreate(
            ['code' => 'assignment_confirmation'],
            [
                'name' => 'PROCTAD Assignment Confirmation Request',
                'subject' => 'Confirmation Required: {exam_name} - {exam_date}',
                'body_html' => '<p>Dear <strong>{member_name}</strong>,</p>'
                    .'<p>You have been assigned as a PROCTAD member for <strong>{exam_name}</strong> on {exam_date}, '
                    .'serving as <strong>{designation}</strong> (PROCTAD ID: {proctad_id}).</p>'
                    .'<p>Please confirm your availability within 7 days:</p>'
                    .'<p><a href="{confirmation_url}">Confirm or Decline Assignment</a></p>'
                    .'<p style="font-size:12px;color:#666">This link expires in 7 days. '
                    .'If you did not expect this assignment, contact your Testing Center.</p>',
                'body_plain' => "Dear {member_name},\n\nYou have been assigned as a PROCTAD member for {exam_name} on {exam_date}, "
                    ."serving as {designation} (PROCTAD ID: {proctad_id}).\n\n"
                    ."Please confirm or decline within 7 days: {confirmation_url}",
                'variables' => $variables,
                'is_active' => true,
            ],
        );

        EmailTemplate::query()->firstOrCreate(
            ['code' => 'assignment_reminder'],
            [
                'name' => 'PROCTAD Assignment Confirmation Reminder',
                'subject' => 'Reminder: Confirm Your Assignment - {exam_name}',
                'body_html' => '<p>Dear <strong>{member_name}</strong>,</p>'
                    .'<p>This is a reminder that you have not yet confirmed your PROCTAD assignment for '
                    .'<strong>{exam_name}</strong> on {exam_date}.</p>'
                    .'<p><a href="{confirmation_url}">Confirm or Decline Assignment</a></p>',
                'body_plain' => "Dear {member_name},\n\nReminder: please confirm or decline your assignment for "
                    ."{exam_name} on {exam_date}: {confirmation_url}",
                'variables' => $variables,
                'is_active' => true,
            ],
        );
    }
}
