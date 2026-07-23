<?php

namespace Database\Seeders;

use App\Enums\BlacklistStatus;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Blacklist;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Bulks up Member data with a realistic 6-month registration spread so the
 * admin dashboard's analytics (trend chart, per-Field-Office breakdown,
 * status breakdown, recent registrations feed) have something to show
 * beyond MemberSeeder's flat 18-member same-day baseline. Not part of the
 * main DatabaseSeeder chain — run on demand:
 *
 *   php artisan db:seed --class=DashboardDemoDataSeeder
 */
class DashboardDemoDataSeeder extends Seeder
{
    /** Registrations per month per office, oldest to newest — an upward trend. */
    private const MONTHLY_COUNTS = [3, 4, 5, 6, 8, 10];

    public function run(): void
    {
        $offices = FieldOffice::where('code', '!=', 'RO8')->get();

        if ($offices->isEmpty()) {
            $this->command?->warn('No field offices found — run FieldOfficeSeeder first.');

            return;
        }

        $this->createMembers($offices);
        $this->createBlacklistEntries();

        $this->command?->info('Dashboard demo data seeded: members backdated across 6 months, plus sample blacklist entries.');
    }

    private function createMembers($offices): void
    {
        foreach ($offices as $office) {
            foreach (self::MONTHLY_COUNTS as $monthsAgo => $count) {
                $monthStart = now()->subMonths(count(self::MONTHLY_COUNTS) - 1 - $monthsAgo)->startOfMonth();

                Member::factory()
                    ->count($count)
                    ->sequence(fn ($sequence) => ['status' => $this->weightedStatus($sequence->index)])
                    ->create(['field_office_id' => $office->id])
                    ->each(function (Member $member) use ($monthStart) {
                        $registeredAt = Carbon::instance($monthStart)->addDays(random_int(0, 27))->addHours(random_int(8, 17));

                        $member->forceFill(['created_at' => $registeredAt, 'updated_at' => $registeredAt])->saveQuietly();

                        if ($member->status === MemberStatus::Disqualified) {
                            $member->forceFill(['disqualification_remarks' => 'Seeded demo disqualification record.'])->saveQuietly();
                        }
                    });
            }
        }
    }

    /** Mostly Active, a handful Inactive/Disqualified — matches real-world proportions. */
    private function weightedStatus(int $index): MemberStatus
    {
        return match (true) {
            $index % 10 === 9 => MemberStatus::Disqualified,
            $index % 10 >= 7 => MemberStatus::Inactive,
            default => MemberStatus::Active,
        };
    }

    private function createBlacklistEntries(): void
    {
        $admin = User::where('role', UserRole::SuperAdmin)->first();

        if (! $admin) {
            return;
        }

        $reasons = [
            'Repeated tardiness during assigned examination shifts.',
            'Failure to report for confirmed assignment without prior notice.',
            'Misconduct reported by Supervising Examiner during exam proper.',
        ];

        Member::where('status', MemberStatus::Active)
            ->whereDoesntHave('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))
            ->inRandomOrder()
            ->limit(3)
            ->get()
            ->each(function (Member $member, int $i) use ($admin, $reasons) {
                Blacklist::create([
                    'member_id' => $member->id,
                    'field_office_id' => $member->field_office_id,
                    'reason' => $reasons[$i % count($reasons)],
                    'status' => BlacklistStatus::Active,
                    'blacklisted_by' => $admin->id,
                    'blacklisted_at' => now()->subDays(random_int(1, 45)),
                ]);
            });
    }
}
