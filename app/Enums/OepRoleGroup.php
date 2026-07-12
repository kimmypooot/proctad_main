<?php

namespace App\Enums;

enum OepRoleGroup: string
{
    case Committee = 'committee';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Committee => 'Testing Committee',
            self::Support => 'Support Personnel',
        };
    }
}
