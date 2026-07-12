<?php

namespace Database\Seeders;

use App\Enums\PersonnelType;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\NonExamPersonnel;
use Illuminate\Database\Seeder;

class NonExamPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        if (NonExamPersonnel::exists()) {
            return;
        }

        $types = [
            PersonnelType::Coordinator, PersonnelType::Inspector, PersonnelType::Paymaster,
            PersonnelType::PnpOfficer, PersonnelType::SecurityOfficer, PersonnelType::Janitor,
            PersonnelType::Helper, PersonnelType::Driver,
        ];

        $offices = FieldOffice::where('code', '!=', 'RO8')->get();
        $created = collect();

        foreach ($offices as $officeIndex => $office) {
            foreach (range(1, 2) as $n) {
                $type = $types[($officeIndex * 2 + $n) % count($types)];

                $nep = NonExamPersonnel::create([
                    // DatabaseSeeder runs under WithoutModelEvents, which suppresses
                    // the creating() hook that normally generates this (see MemberFactory
                    // for the same fix applied to Member::proctad_id).
                    'nep_id' => NonExamPersonnel::generateNepId(),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'sex' => fake()->randomElement(['male', 'female']),
                    'contact_number' => '09'.fake()->numerify('#########'),
                    'personnel_type' => $type,
                    'field_office_id' => $office->id,
                    'is_active' => true,
                ]);

                $created->push($nep);
            }
        }

        $this->attachToVenues($created);
    }

    private function attachToVenues(\Illuminate\Support\Collection $personnel): void
    {
        $past = Examination::where('title', 'March 2026 CSE-PPT')->first();

        if (! $past) {
            return;
        }

        $venues = $past->venues()->get();

        if ($venues->isEmpty()) {
            return;
        }

        foreach ($personnel->take(4) as $index => $nep) {
            $venue = $venues[$index % $venues->count()];

            $venue->nepAssignments()->create([
                'non_exam_personnel_id' => $nep->id,
                'status' => 'confirmed',
            ]);

            // Mark the first couple as having actually shown up.
            if ($index < 2) {
                $nep->attendances()->create([
                    'examination_school_id' => $venue->id,
                    'status' => 'present',
                    'scan_method' => 'manual',
                    'scanned_at' => '2026-03-15 06:15:00',
                ]);
            }
        }
    }
}
