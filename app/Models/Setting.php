<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'type', 'description', 'is_public', 'updated_by'])]
class Setting extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => Cache::forget("settings.{$setting->key}"));
        static::deleted(fn (self $setting) => Cache::forget("settings.{$setting->key}"));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if ($setting === null) {
                return $default;
            }

            return match ($setting->type) {
                'number' => (float) $setting->value,
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?int $updatedBy = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $type === 'json' ? json_encode($value) : (string) $value,
                'type' => $type,
                'updated_by' => $updatedBy,
            ],
        );
    }
}
