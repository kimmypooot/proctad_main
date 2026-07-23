<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Baseline templates for every system-sent email (spec / D5). Ported from the
 * legacy proctad_email_templates rows so fresh installs (and installs run
 * before the legacy ETL) still have working templates. The legacy ETL
 * imports the real, field-office-customized copies of these over these seeds
 * when it runs.
 *
 * All three templates share one branded HTML shell (buildEmail()) so the
 * look stays consistent without staff needing to hand-craft markup per
 * template in the admin UI — only the message body changes between them.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $confirmationVariables = [
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
                'body_html' => $this->buildEmail(
                    eyebrow: 'Assignment Confirmation',
                    eyebrowColor: '#8f9adf',
                    body: '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#334155;">Dear <strong>{member_name}</strong>,</p>'
                        .'<p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#334155;">'
                        .'You have been assigned as a PROCTAD member for an upcoming examination. Please review the '
                        .'details below and confirm your availability within 7 days.</p>'
                        .$this->infoCard([
                            'Examination' => '{exam_name}',
                            'Date' => '{exam_date}',
                            'Role' => '{designation}',
                            'PROCTAD ID' => '{proctad_id}',
                        ])
                        .$this->button('Confirm or Decline Assignment', '{confirmation_url}')
                        .'<p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#94a3b8;">'
                        .'This link expires in 7 days. If you did not expect this assignment, please contact your '
                        .'Field Office.</p>',
                ),
                'body_plain' => "Dear {member_name},\n\nYou have been assigned as a PROCTAD member for {exam_name} on {exam_date}, "
                    ."serving as {designation} (PROCTAD ID: {proctad_id}).\n\n"
                    ."Please confirm or decline within 7 days: {confirmation_url}",
                'variables' => $confirmationVariables,
                'is_active' => true,
            ],
        );

        EmailTemplate::query()->firstOrCreate(
            ['code' => 'assignment_reminder'],
            [
                'name' => 'PROCTAD Assignment Confirmation Reminder',
                'subject' => 'Reminder: Confirm Your Assignment - {exam_name}',
                'body_html' => $this->buildEmail(
                    eyebrow: 'Reminder',
                    eyebrowColor: '#f6a9b0',
                    body: '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#334155;">Dear <strong>{member_name}</strong>,</p>'
                        .'<p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#334155;">'
                        .'This is a friendly reminder that we have not yet received your confirmation for the '
                        .'assignment below.</p>'
                        .$this->infoCard([
                            'Examination' => '{exam_name}',
                            'Date' => '{exam_date}',
                        ])
                        .$this->button('Confirm or Decline Assignment', '{confirmation_url}'),
                ),
                'body_plain' => "Dear {member_name},\n\nReminder: please confirm or decline your assignment for "
                    ."{exam_name} on {exam_date}: {confirmation_url}",
                'variables' => $confirmationVariables,
                'is_active' => true,
            ],
        );

        EmailTemplate::query()->firstOrCreate(
            ['code' => 'certificate_released'],
            [
                'name' => 'Certificate Released',
                'subject' => 'Your {certificate_type} Has Been Released',
                'body_html' => $this->buildEmail(
                    eyebrow: 'Certificate Released',
                    eyebrowColor: '#8f9adf',
                    body: '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#334155;">Dear <strong>{member_name}</strong>,</p>'
                        .'<p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#334155;">'
                        .'Congratulations! Your <strong>{certificate_type}</strong> has been approved and '
                        .'electronically released by the Civil Service Commission Regional Office VIII.</p>'
                        .$this->infoCard([
                            'Certificate No.' => '{certificate_no}',
                            'Event' => '{source_description}',
                            'Date' => '{source_date}',
                            'PROCTAD ID' => '{proctad_id}',
                        ])
                        .'<p style="margin:24px 0 0;font-size:15px;line-height:1.7;color:#334155;">'
                        .'The certificate is attached to this email as a PDF. You can also access it anytime from '
                        .'your PROCTAD account.</p>'
                        .$this->button('View My Certificates', '{portal_url}')
                        .'<p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#94a3b8;">'
                        .'You may verify its authenticity at any time by scanning the QR code on the document.</p>',
                ),
                'body_plain' => "Dear {member_name},\n\nYour {certificate_type} ({certificate_no}) has been approved and "
                    ."electronically released by the Civil Service Commission Regional Office VIII.\n\n"
                    ."Event: {source_description}\nDate: {source_date}\nPROCTAD ID: {proctad_id}\n\n"
                    ."The certificate is attached to this email as a PDF. You can also access it anytime from your "
                    ."PROCTAD account: {portal_url}",
                'variables' => [
                    'member_name' => 'Full name of the PROCTAD member',
                    'certificate_type' => 'Certificate type label',
                    'certificate_no' => 'Certificate number',
                    'source_description' => 'Description of the exam/training the certificate is for',
                    'source_date' => 'Date of the source exam/training',
                    'proctad_id' => 'Member ID',
                    'portal_url' => 'Link to the member\'s My Certificates page',
                ],
                'is_active' => true,
            ],
        );
    }

    /**
     * Shared branded shell: CSC RO VIII / PROCTAD header, a white card body,
     * and a muted footer. Poppins is requested via Google Fonts for clients
     * that render remote @import/webfonts; everything also carries an inline
     * system-sans fallback stack for clients that don't (e.g. Outlook desktop).
     */
    private function buildEmail(string $eyebrow, string $eyebrowColor, string $body): string
    {
        $fontStack = "'Poppins',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif";

        return <<<HTML
            <!doctype html>
            <html>
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
                body, table, td, a { font-family: {$fontStack} !important; }
                .btn:hover { background-color: #232b78 !important; }
            </style>
            </head>
            <body style="margin:0;padding:0;background-color:#f4f5f9;font-family:{$fontStack};">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f9;padding:32px 16px;">
                    <tr>
                        <td align="center">
                            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(11,11,11,0.08);">
                                <tr>
                                    <td style="background-color:#2A338F;padding:28px 40px;text-align:center;">
                                        <p style="margin:0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#b9c0ec;font-weight:500;">Republic of the Philippines &middot; Civil Service Commission</p>
                                        <p style="margin:4px 0 0;font-size:18px;font-weight:700;color:#ffffff;">Regional Office VIII</p>
                                        <p style="margin:2px 0 0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:{$eyebrowColor};">PROCTAD &middot; Professionalized Corps of Test Administrators</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 40px 0;">
                                        <p style="margin:24px 0 4px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#3d4bae;">{$eyebrow}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 40px 36px;">
                                        {$body}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:24px 40px;border-top:1px solid #e2e4ea;text-align:center;">
                                        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated message from the PROCTAD system. Please do not reply directly to this email.</p>
                                        <p style="margin:6px 0 0;font-size:12px;color:#94a3b8;">CSC Regional Office VIII &middot; PROCTAD Program</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }

    /** A clean key/value "receipt" card for the details relevant to the email. */
    private function infoCard(array $rows): string
    {
        $cells = collect($rows)->map(fn ($value, $label) => <<<HTML
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #eef0fb;font-size:13px;color:#64748b;width:40%;">{$label}</td>
                <td style="padding:10px 0;border-bottom:1px solid #eef0fb;font-size:14px;font-weight:600;color:#1e293b;">{$value}</td>
            </tr>
            HTML)->implode('');

        return <<<HTML
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;background-color:#f9f9fb;border-radius:10px;padding:4px 20px;">
                {$cells}
            </table>
            HTML;
    }

    /** The brand-navy call-to-action button, used for confirmation/decline and portal links. */
    private function button(string $label, string $url): string
    {
        return <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 0;">
                <tr>
                    <td style="border-radius:8px;background-color:#2A338F;">
                        <a href="{$url}" class="btn" style="display:inline-block;padding:13px 32px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">{$label}</a>
                    </td>
                </tr>
            </table>
            HTML;
    }
}
