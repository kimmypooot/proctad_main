<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\OtherExaminationPersonnel;
use Illuminate\Console\Command;

/**
 * One-off backfill: Member/OtherExaminationPersonnel now uppercase first/middle/last
 * name and suffix automatically on write (see their name-attribute mutators),
 * but that only affects new writes going forward — this normalizes existing
 * records still stored in mixed case.
 */
class NormalizeNameCasing extends Command
{
    protected $signature = 'proctad:normalize-name-casing';

    protected $description = 'Uppercase existing member and OEP name fields to match the automatic-uppercase mutators';

    public function handle(): int
    {
        $memberCount = 0;
        Member::withTrashed()->chunkById(200, function ($members) use (&$memberCount) {
            foreach ($members as $member) {
                $member->first_name = $member->first_name;
                $member->middle_name = $member->middle_name;
                $member->last_name = $member->last_name;
                $member->suffix = $member->suffix;
                if ($member->isDirty()) {
                    $member->save();
                    $memberCount++;
                }
            }
        });

        $oepCount = 0;
        OtherExaminationPersonnel::withTrashed()->chunkById(200, function ($people) use (&$oepCount) {
            foreach ($people as $oep) {
                $oep->first_name = $oep->first_name;
                $oep->middle_name = $oep->middle_name;
                $oep->last_name = $oep->last_name;
                $oep->suffix = $oep->suffix;
                if ($oep->isDirty()) {
                    $oep->save();
                    $oepCount++;
                }
            }
        });

        $this->info("Normalized {$memberCount} member(s) and {$oepCount} other examination personnel record(s).");

        return self::SUCCESS;
    }
}
