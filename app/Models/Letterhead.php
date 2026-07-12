<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'file_path', 'is_active', 'uploaded_by'])]
class Letterhead extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPdf(): bool
    {
        return str_ends_with(strtolower($this->file_path), '.pdf');
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }

    /** Activate this letterhead, deactivating all others. */
    public function activate(): void
    {
        static::query()->whereKeyNot($this->getKey())->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
