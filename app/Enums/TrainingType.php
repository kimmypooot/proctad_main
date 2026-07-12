<?php

namespace App\Enums;

enum TrainingType: string
{
    case Tea = 'tea';
    case Briefing = 'briefing';

    public function label(): string
    {
        return match ($this) {
            self::Tea => 'Training on Examination Administration (TEA)',
            self::Briefing => 'Briefing on Conduct of Examination',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Tea => 'TEA',
            self::Briefing => 'Briefing',
        };
    }
}
