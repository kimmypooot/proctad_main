<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Pending = 'pending';
    case Released = 'released';
    case Disapproved = 'disapproved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Released => 'Released',
            self::Disapproved => 'Disapproved',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Released => 'success',
            self::Disapproved => 'neutral',
        };
    }
}
