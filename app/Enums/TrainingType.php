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

    /**
     * TEA is a course a participant completes, so confirmed attendance earns a
     * Certificate of Completion on top of the Appearance certificate every
     * training issues. A Briefing is an information session, not a course —
     * there is nothing to complete, so it earns Appearance only.
     */
    public function issuesCompletionCertificate(): bool
    {
        return $this === self::Tea;
    }
}
