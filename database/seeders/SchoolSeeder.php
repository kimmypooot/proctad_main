<?php

namespace Database\Seeders;

use App\Models\FieldOffice;
use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (School::exists()) {
            return;
        }

        $schoolsByOfficeCode = [
            'LEY' => [
                ['name' => 'Leyte National High School', 'municipality' => 'Tacloban City'],
                ['name' => 'Eastern Visayas State University', 'municipality' => 'Tacloban City'],
                ['name' => 'Leyte Normal University', 'municipality' => 'Tacloban City'],
            ],
            'SLE' => [
                ['name' => 'Saint Joseph College', 'municipality' => 'Maasin City'],
                ['name' => 'Southern Leyte National High School', 'municipality' => 'Maasin City'],
            ],
            'BIL' => [
                ['name' => 'Biliran Province State University', 'municipality' => 'Naval'],
                ['name' => 'Naval National High School', 'municipality' => 'Naval'],
            ],
            'SAM' => [
                ['name' => 'Samar National School', 'municipality' => 'Catbalogan City'],
                ['name' => 'Northwest Samar State University', 'municipality' => 'Calbayog City'],
            ],
            'ESA' => [
                ['name' => 'Eastern Samar State University', 'municipality' => 'Borongan City'],
                ['name' => 'Eastern Samar National Comprehensive High School', 'municipality' => 'Borongan City'],
            ],
            'NSA' => [
                ['name' => 'Catarman National High School', 'municipality' => 'Catarman'],
                ['name' => 'University of Eastern Philippines', 'municipality' => 'Catarman'],
            ],
        ];

        foreach ($schoolsByOfficeCode as $officeCode => $schools) {
            $office = FieldOffice::where('code', $officeCode)->first();

            if (! $office) {
                continue;
            }

            foreach ($schools as $school) {
                School::create([
                    ...$school,
                    'field_office_id' => $office->id,
                    'contact_person' => null,
                    'contact_number' => null,
                    'contact_email' => null,
                    'is_active' => true,
                ]);
            }
        }
    }
}
