<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\TrainingSession;
use App\Enums\TrainingType;
use App\Models\Member;
use App\Models\Training;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        if (Training::exists()) {
            return;
        }

        // Concluded TEA with confirmed attendance. Left "not completed" in the
        // system so Completion-certificate issuance can be demonstrated live.
        $tea = Training::create([
            'title' => 'TEA Batch 1 — 2026',
            'type' => TrainingType::Tea,
            'training_date' => '2026-02-10',
            'session' => TrainingSession::Am,
            'venue' => 'CSC RO VIII Training Hall, Palo, Leyte',
        ]);

        $upcoming = Training::create([
            'title' => 'Pre-Exam Briefing — August 2026 CSE-PPT',
            'type' => TrainingType::Briefing,
            'training_date' => '2026-08-02',
            'venue' => 'Leyte Testing Center',
        ]);

        $members = Member::where('status', MemberStatus::Active)->get();

        foreach ($members as $index => $member) {
            $tea->assignments()->create([
                'member_id' => $member->id,
                'field_office_id' => $member->field_office_id,
                'attendance_confirmed_at' => $index % 3 === 2 ? null : '2026-02-10 08:15:00',
            ]);

            if ($index % 2 === 0) {
                $upcoming->assignments()->create([
                    'member_id' => $member->id,
                    'field_office_id' => $member->field_office_id,
                ]);
            }
        }
    }
}
