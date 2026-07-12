<x-mail::message>
# {{ $certificate->type->label() }}

Dear {{ $certificate->member->name }},

Your **{{ $certificate->type->label() }}** ({{ $certificate->certificate_no }}) has been approved and electronically released by the Civil Service Commission Regional Office VIII.

- **Event:** {{ $certificate->sourceDescription() }}
- **Date:** {{ $certificate->sourceDate() ?? '—' }}
- **PROCTAD ID:** {{ $certificate->member->proctad_id }}

The certificate is attached to this email as a PDF. You can also access it anytime from your PROCTAD account under **My Certificates**.

You may verify its authenticity at any time by scanning the QR code on the document.

Thank you for your service,<br>
CSC Regional Office VIII — PROCTAD
</x-mail::message>
