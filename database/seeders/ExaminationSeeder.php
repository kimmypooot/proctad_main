<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\ExamRoleGroup;
use App\Enums\MemberStatus;
use App\Enums\PerformanceRating;
use App\Models\Examination;
use App\Models\ExamType;
use App\Models\Member;
use App\Models\School;
use Illuminate\Database\Seeder;

class ExaminationSeeder extends Seeder
{
    public function run(): void
    {
        if (Examination::exists()) {
            return;
        }

        $cseType = ExamType::where('name', 'like', 'Career Service Examination - Pen%')->first();
        $foeType = ExamType::where('name', 'like', 'Fire Officer%')->first();

        $past = Examination::create([
            'title' => 'March 2026 CSE-PPT',
            'type' => 'CSE-PPT Professional',
            'exam_type_id' => $cseType?->id,
            'exam_date' => '2026-03-15',
        ]);

        $upcoming = Examination::create([
            'title' => 'August 2026 CSE-PPT',
            'type' => 'CSE-PPT Professional',
            'exam_type_id' => $cseType?->id,
            'exam_date' => '2026-08-09',
        ]);

        $cancelled = Examination::create([
            'title' => 'January 2026 Fire Officer Examination',
            'type' => 'Fire Officer Examination',
            'exam_type_id' => $foeType?->id,
            'exam_date' => '2026-01-20',
        ]);

        $this->attachVenuesAndRooms($past);
        $venues = $this->attachVenuesAndRooms($upcoming);

        $schoolRoles = ExamRole::inGroup(ExamRoleGroup::School);
        $regionalRoles = ExamRole::inGroup(ExamRoleGroup::Regional);
        $ratings = [PerformanceRating::Outstanding, PerformanceRating::VerySatisfactory, PerformanceRating::Satisfactory];

        $members = Member::where('status', MemberStatus::Active)->get();

        foreach ($members as $index => $member) {
            $role = $index === 0
                ? $regionalRoles[0] // one REC-level assignment for variety
                : $schoolRoles[$index % count($schoolRoles)];

            // Past exam: fully attended with a rating — populates service history & certificate demos.
            $past->assignments()->create([
                'member_id' => $member->id,
                'role' => $role,
                'field_office_id' => $member->field_office_id,
                'status' => AssignmentStatus::Confirmed,
                'confirmation_sent_at' => '2026-03-01 08:00:00',
                'responded_at' => '2026-03-02 09:00:00',
                'attendance_confirmed_at' => '2026-03-15 06:30:00',
                'performance_rating' => $ratings[$index % count($ratings)],
            ]);

            // Upcoming exam: mixed confirmation states so the confirmation workflow
            // and the assignments table have something to demonstrate.
            if ($index % 2 === 0) {
                $venue = $venues[$index % max(count($venues), 1)] ?? null;

                $status = match ($index % 4) {
                    0 => AssignmentStatus::Confirmed,
                    2 => AssignmentStatus::Pending,
                    default => AssignmentStatus::Pending,
                };

                $upcoming->assignments()->create([
                    'member_id' => $member->id,
                    'role' => $role,
                    'field_office_id' => $member->field_office_id,
                    'examination_school_id' => $venue?->id,
                    'status' => $status,
                    'confirmation_sent_at' => $status !== AssignmentStatus::Pending || $index % 8 === 0 ? '2026-07-20 08:00:00' : null,
                    'responded_at' => $status === AssignmentStatus::Confirmed ? '2026-07-21 10:00:00' : null,
                ]);
            } elseif ($index % 7 === 0) {
                // A couple of declined assignments for realism.
                $upcoming->assignments()->create([
                    'member_id' => $member->id,
                    'role' => $role,
                    'field_office_id' => $member->field_office_id,
                    'status' => AssignmentStatus::Declined,
                    'confirmation_sent_at' => '2026-07-18 08:00:00',
                    'responded_at' => '2026-07-19 14:00:00',
                    'decline_reason' => 'Conflicting official travel.',
                ]);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\ExaminationSchool> */
    private function attachVenuesAndRooms(Examination $examination): \Illuminate\Support\Collection
    {
        $schools = School::where('is_active', true)->inRandomOrder()->limit(2)->get();
        $venues = collect();

        foreach ($schools as $school) {
            $venue = $examination->venues()->create(['school_id' => $school->id]);

            foreach (range(1, 3) as $n) {
                $venue->rooms()->create([
                    'room_number' => sprintf('Room-%03d', $n),
                    'capacity' => 25,
                    'designation' => $n === 1 ? 'Professional' : 'Sub-Professional',
                ]);
            }

            $venues->push($venue);
        }

        return $venues;
    }
}
