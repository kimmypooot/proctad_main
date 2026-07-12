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
     * @param  array<string, string>  $data
     * @return array{subject: string, html: string, plain: ?string}
     */
    public function render(array $data): array
    {
        $replace = fn (?string $text): ?string => $text === null
            ? null
            : preg_replace_callback(
                '/\{(\w+)\}/',
                fn (array $m) => $data[$m[1]] ?? $m[0],
                $text,
            );

        return [
            'subject' => $replace($this->subject),
            'html' => $replace($this->body_html),
            'plain' => $replace($this->body_plain),
        ];
    }
}
