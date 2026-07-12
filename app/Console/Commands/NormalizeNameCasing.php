<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\NonExamPersonnel;
use Illuminate\Console\Command;

/**
 * One-off backfill: Member/NonExamPersonnel now uppercase first/middle/last
 * name and suffix automatically on write (see their name-attribute mutators),
 * but that only affects new writes going forward — this normalizes existing
 * records still stored in mixed case.
 */
class NormalizeNameCasing extends Command
{
    protected $signature = 'proctad:normalize-name-casing';

    protected $description = 'Uppercase existing member and NEP name fields to match the automatic-uppercase mutators';

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

        $nepCount = 0;
        NonExamPersonnel::withTrashed()->chunkById(200, function ($people) use (&$nepCount) {
            foreach ($people as $nep) {
                $nep->first_name = $nep->first_name;
                $nep->middle_name = $nep->middle_name;
                $nep->last_name = $nep->last_name;
                $nep->suffix = $nep->suffix;
                if ($nep->isDirty()) {
                    $nep->save();
                    $nepCount++;
                }
            }
        });

        $this->info("Normalized {$memberCount} member(s) and {$nepCount} non-exam personnel record(s).");

        return self::SUCCESS;
    }
}
