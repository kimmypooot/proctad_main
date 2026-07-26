<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'subject', 'body_html', 'body_plain', 'variables', 'is_active'])]
class EmailTemplate extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Substitute {placeholder} variables into subject, HTML, and plain bodies.
     *
     * Values substituted into the HTML body are escaped. They are not all
     * system-generated: member_name, and anything else drawn from a Member
     * record, is text the member edits themselves at /my/profile. Unescaped, a
     * member could put an anchor tag in their agency or position and have it
     * delivered inside a genuine CSC email — correctly signed, correctly
     * branded, pointing wherever they liked.
     *
     * The plain-text body is left alone: entity-encoding there reaches the
     * reader as a literal "&amp;". Escaping a URL for the HTML body is safe —
     * "&amp;" inside an href is valid and the browser decodes it.
     *
     * @param  array<string, string>  $data
     * @return array{subject: string, html: string, plain: ?string}
     */
    public function render(array $data): array
    {
        $replace = fn (?string $text, bool $escape): ?string => $text === null
            ? null
            : preg_replace_callback(
                '/\{(\w+)\}/',
                fn (array $m) => array_key_exists($m[1], $data)
                    ? ($escape ? e($data[$m[1]]) : $data[$m[1]])
                    : $m[0],
                $text,
            );

        return [
            // CR/LF stripped defensively: a newline in a subject is the classic
            // header-injection primitive. Symfony Mailer rejects them too, but
            // a rejected message is an exception mid-send rather than a clean
            // subject line.
            'subject' => str_replace(["\r", "\n"], '', (string) $replace($this->subject, false)),
            'html' => $replace($this->body_html, true),
            'plain' => $replace($this->body_plain, false),
        ];
    }
}
