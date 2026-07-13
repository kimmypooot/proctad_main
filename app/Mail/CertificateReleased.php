<?php

namespace App\Mail;

use App\Models\Certificate;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Subject/body are sourced entirely from the admin-editable 'certificate_released'
 * EmailTemplate row (see EmailTemplateSeeder / /email-templates) rather than a
 * hardcoded Blade view — kept as a Mailable class (not routed through
 * NotificationMailer) only because it needs to attach the certificate PDF,
 * which the template system doesn't handle.
 */
class CertificateReleased extends Mailable
{
    use Queueable, SerializesModels;

    private array $rendered;

    public function __construct(public Certificate $certificate)
    {
        $member = $certificate->member;

        $data = [
            'member_name' => $member->nameFirstLast(),
            'certificate_type' => $certificate->type->label(),
            'certificate_no' => $certificate->certificate_no,
            'source_description' => $certificate->sourceDescription(),
            'source_date' => $certificate->sourceDate() ?? '—',
            'proctad_id' => $member->proctad_id,
            'portal_url' => route('my.certificates'),
        ];

        $template = EmailTemplate::where('code', 'certificate_released')->where('is_active', true)->first();

        $this->rendered = $template
            ? $template->render($data)
            : [
                'subject' => "{$certificate->type->label()} — {$certificate->certificate_no}",
                'html' => "<p>Dear {$data['member_name']},</p><p>Your {$data['certificate_type']} has been released. "
                    .'Please check your PROCTAD account.</p>',
            ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered['subject']);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->rendered['html']);
    }

    public function attachments(): array
    {
        if (! $this->certificate->pdf_path || ! Storage::disk('local')->exists($this->certificate->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->certificate->pdf_path)
                ->as("{$this->certificate->certificate_no}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
