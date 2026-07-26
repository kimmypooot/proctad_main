<?php

namespace Database\Seeders;

use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Models\FieldOffice;
use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        if (! Member::exists()) {
            $this->createSampleMembers();
        }

        $this->linkMemberTestAccount();
    }

    private function createSampleMembers(): void
    {
        $offices = FieldOffice::where('code', '!=', 'RO8')->get();
        $statuses = [
            MemberStatus::Active, MemberStatus::Active, MemberStatus::Active,
            MemberStatus::Inactive, MemberStatus::Disqualified,
        ];

        foreach ($offices as $office) {
            Member::factory()
                ->count(3)
                ->sequence(fn ($sequence) => ['status' => $statuses[$sequence->index % count($statuses)]])
                ->create(['field_office_id' => $office->id])
                ->each(function (Member $member) {
                    if ($member->status === MemberStatus::Disqualified) {
                        $member->update(['disqualification_remarks' => 'Seeded disqualification record.']);
                    }

                    foreach (EligibilityRequirement::cases() as $index => $requirement) {
                        $member->requirements()->create([
                            'requirement' => $requirement,
                            'complied' => $index < random_int(3, 7),
                        ]);
                    }
                });
        }
    }

    /**
     * Link the seeded member test account to one active Leyte member so the
     * self-service pages have data to show.
     */
    private function linkMemberTestAccount(): void
    {
        $memberAccount = \App\Models\User::where('email', 'member@proctad.test')->first();

        if (! $memberAccount || Member::where('user_id', $memberAccount->id)->exists()) {
            return;
        }

        Member::where('status', MemberStatus::Active)
            ->whereHas('fieldOffice', fn ($q) => $q->where('code', 'FOLI-TAC'))
            ->first()
            ?->update(['user_id' => $memberAccount->id]);
    }
}
