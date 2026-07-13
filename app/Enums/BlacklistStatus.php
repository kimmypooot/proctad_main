<?php

namespace App\Enums;

enum BlacklistStatus: string
{
    case Active = 'active';
    case Lifted = 'lifted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Blacklisted',
            self::Lifted => 'Lifted',
        };
    }

    /**
     * Matching BaseBadge.vue variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'accent',
            self::Lifted => 'neutral',
        };
    }
}
