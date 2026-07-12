<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an admin-editable EmailTemplate (see Setting/EmailTemplate models)
 * with {placeholder} variables substituted. Owner requirement: email content
 * must remain editable at runtime without a code deploy.
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    private array $rendered;

    /** @param array<string, string> $data */
    public function __construct(EmailTemplate $template, array $data)
    {
        $this->rendered = $template->render($data);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered['subject']);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->rendered['html']);
    }
}
