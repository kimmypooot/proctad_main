<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends admin-editable email templates and logs every attempt to email_logs
 * (mirrors the legacy proctad_email_logs audit trail).
 */
class NotificationMailer
{
    /**
     * @param  array<string, string>  $data  {placeholder} substitutions for the template
     */
    public function send(
        string $templateCode,
        string $toEmail,
        ?string $toName,
        string $emailType,
        array $data,
        ?User $sentBy = null,
    ): EmailLog {
        $template = EmailTemplate::where('code', $templateCode)->where('is_active', true)->first();

        if ($template === null) {
            return EmailLog::create([
                'recipient_email' => $toEmail,
                'recipient_name' => $toName,
                'subject' => "[{$templateCode}]",
                'email_type' => $emailType,
                'status' => 'failed',
                'error_message' => "No active email template found for code '{$templateCode}'.",
                'sent_by' => $sentBy?->id,
            ]);
        }

        $rendered = $template->render($data);

        // Email is globally paused (Settings → General). AppServiceProvider has
        // already redirected the mailer to 'log', so sending would "succeed" and
        // record a misleading 'sent' row — log it as skipped instead, so the
        // audit trail reflects that nothing was delivered.
        if (! Setting::emailSendingEnabled()) {
            return EmailLog::create([
                'recipient_email' => $toEmail,
                'recipient_name' => $toName,
                'subject' => $rendered['subject'],
                'email_type' => $emailType,
                'status' => 'skipped',
                'error_message' => 'Email sending is switched off in Settings.',
                'sent_by' => $sentBy?->id,
            ]);
        }

        try {
            Mail::to($toEmail)->send(new TemplatedMail($template, $data));

            return EmailLog::create([
                'recipient_email' => $toEmail,
                'recipient_name' => $toName,
                'subject' => $rendered['subject'],
                'email_type' => $emailType,
                'status' => 'sent',
                'sent_by' => $sentBy?->id,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            return EmailLog::create([
                'recipient_email' => $toEmail,
                'recipient_name' => $toName,
                'subject' => $rendered['subject'],
                'email_type' => $emailType,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_by' => $sentBy?->id,
            ]);
        }
    }
}
