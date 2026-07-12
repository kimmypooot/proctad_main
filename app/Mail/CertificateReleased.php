<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificateReleased extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Certificate $certificate) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->certificate->type->label()} — {$this->certificate->certificate_no}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.certificate-released');
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
