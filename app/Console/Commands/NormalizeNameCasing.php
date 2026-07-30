<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\OtherExaminationPersonnel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * One-off backfill: Member/OtherExaminationPersonnel uppercase their name parts
 * — and, since the agency and position mutators were added, those too — on
 * write, but that only affects new writes going forward. This normalizes
 * existing records still stored in mixed case.
 *
 * Re-runnable, and cheap to re-run: assigning each attribute back to itself
 * passes it through the mutator, and only a row the mutator actually changed
 * comes out dirty, so a record already uppercase is skipped without a write.
 */
class NormalizeNameCasing extends Command
{
    protected $signature = 'proctad:normalize-name-casing';

    protected $description = 'Uppercase existing member and OEP name, agency and position fields to match the automatic-uppercase mutators';

    /** The uppercased-on-write attributes, identical on both models. */
    private const FIELDS = ['first_name', 'middle_name', 'last_name', 'suffix', 'agency', 'position'];

    public function handle(): int
    {
        $memberCount = $this->normalize(Member::withTrashed());
        $oepCount = $this->normalize(OtherExaminationPersonnel::withTrashed());

        $this->info("Normalized {$memberCount} member(s) and {$oepCount} other examination personnel record(s).");

        return self::SUCCESS;
    }

    private function normalize(Builder $query): int
    {
        $count = 0;

        $query->chunkById(200, function ($records) use (&$count) {
            foreach ($records as $record) {
                foreach (self::FIELDS as $field) {
                    $record->{$field} = $record->{$field};
                }

                if ($record->isDirty()) {
                    $record->save();
                    $count++;
                }
            }
        });

        return $count;
    }
}
