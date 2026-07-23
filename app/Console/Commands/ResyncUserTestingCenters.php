<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Re-derive every user's testing-center links from their field office.
 *
 * User → testing-center links are kept in step on save by UserObserver, but
 * that only fires when a user is written. When a field office instead gains a
 * new testing center, its existing users don't automatically pick it up — this
 * catches them all up in one pass. Idempotent: safe to run any time.
 */
class ResyncUserTestingCenters extends Command
{
    protected $signature = 'proctad:resync-user-testing-centers';

    protected $description = 'Re-derive every user\'s testing-center links from their field office (e.g. after an office gains a new center)';

    public function handle(): int
    {
        $total = 0;
        $changed = 0;

        User::query()->chunkById(200, function ($users) use (&$total, &$changed) {
            foreach ($users as $user) {
                $result = $user->syncTestingCentersFromFieldOffice();
                $total++;

                if ($result['attached'] || $result['detached']) {
                    $changed++;
                }
            }
        });

        $this->info("Resynced {$total} user(s); {$changed} had their testing-center links updated.");

        return self::SUCCESS;
    }
}
