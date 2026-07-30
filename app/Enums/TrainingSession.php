<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Which half of the day a training runs.
 *
 * Volume decides this: a batch too large for one room is split into an AM and a
 * PM sitting of the same course, and each sitting is its own Training row. That
 * keeps one roster, one scanner link and one attendance count per sitting
 * without threading a session id through the scanner, the exports and the
 * certificates — see the session-scoped link expiry below for why the split
 * has to be real rather than cosmetic.
 */
enum TrainingSession: string
{
    case Am = 'am';
    case Pm = 'pm';
    case WholeDay = 'whole_day';

    public function label(): string
    {
        return match ($this) {
            self::Am => 'AM session',
            self::Pm => 'PM session',
            self::WholeDay => 'Whole day',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Am => 'AM',
            self::Pm => 'PM',
            self::WholeDay => 'Whole day',
        };
    }

    /**
     * When a sitting on $date stops accepting arrivals.
     *
     * Attendance is time-in only, so this is the point past which a scan can no
     * longer belong to this sitting: an AM link still live at 2pm would quietly
     * write PM arrivals into the AM roster, since ScannerController creates the
     * assignment on scan.
     */
    public function endsAt(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Am => $date->copy()->setTime(12, 0),
            self::Pm, self::WholeDay => $date->copy()->setTime(18, 0),
        };
    }
}
