<?php

namespace Database\Seeders;

use App\Enums\PersonnelType;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\OtherExaminationPersonnel;
use Illuminate\Database\Seeder;

class OtherExaminationPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        if (OtherExaminationPersonnel::exists()) {
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

                $oep = OtherExaminationPersonnel::create([
                    // DatabaseSeeder runs under WithoutModelEvents, which suppresses
                    // the creating() hook that normally generates this (see MemberFactory
                    // for the same fix applied to Member::proctad_id).
                    'oep_id' => OtherExaminationPersonnel::generateOepId(),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'sex' => fake()->randomElement(['male', 'female']),
                    'contact_number' => '09'.fake()->numerify('#########'),
                    'personnel_type' => $type,
                    'field_office_id' => $office->id,
                    'is_active' => true,
                ]);

                $created->push($oep);
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

        foreach ($personnel->take(4) as $index => $oep) {
            $venue = $venues[$index % $venues->count()];

            $venue->oepAssignments()->create([
                'other_examination_personnel_id' => $oep->id,
                'status' => 'confirmed',
            ]);

            // Mark the first couple as having actually shown up.
            if ($index < 2) {
                $oep->attendances()->create([
                    'examination_school_id' => $venue->id,
                    'status' => 'present',
                    'scan_method' => 'manual',
                    'scanned_at' => '2026-03-15 06:15:00',
                ]);
            }
        }
    }
}
