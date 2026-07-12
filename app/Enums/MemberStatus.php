<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Disqualified => 'Disqualified',
        };
    }

    /**
     * Matching BaseBadge.vue variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
            self::Disqualified => 'warning',
        };
    }
}
