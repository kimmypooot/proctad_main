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

    /** Settings key for the outbound-email kill switch. */
    public const EMAIL_SENDING_ENABLED = 'email_sending_enabled';

    /**
     * Whether the system may send outbound email at all. Defaults to true so a
     * missing row (fresh install, unseeded database) never silently swallows
     * mail — it has to be switched off deliberately.
     */
    public static function emailSendingEnabled(): bool
    {
        return (bool) static::get(self::EMAIL_SENDING_ENABLED, true);
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?int $updatedBy = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                // Booleans are normalised to '1'/'0' rather than cast with
                // (string), which turns false into an empty string — readable
                // back via filter_var, but it meant the seeder and the settings
                // UI stored two different representations of "off".
                'value' => match (true) {
                    $type === 'json' => json_encode($value),
                    $type === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                    default => (string) $value,
                },
                'type' => $type,
                'updated_by' => $updatedBy,
            ],
        );
    }
}
