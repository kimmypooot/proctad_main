<?php

namespace App\Enums;

enum PerformanceRating: string
{
    case Outstanding = 'outstanding';
    case VerySatisfactory = 'very_satisfactory';
    case Satisfactory = 'satisfactory';
    case Unsatisfactory = 'unsatisfactory';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Outstanding => 'Outstanding',
            self::VerySatisfactory => 'Very Satisfactory',
            self::Satisfactory => 'Satisfactory',
            self::Unsatisfactory => 'Unsatisfactory',
            self::Poor => 'Poor',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Outstanding, self::VerySatisfactory => 'success',
            self::Satisfactory => 'brand',
            self::Unsatisfactory, self::Poor => 'warning',
        };
    }
}
