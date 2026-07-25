<?php

namespace Database\Seeders;

use App\Models\FieldOffice;
use App\Models\School;
use App\Models\TestingCenter;
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
                $center = TestingCenter::firstOrCreate(['name' => $school['municipality']], ['is_active' => true]);
                $center->fieldOffices()->syncWithoutDetaching([$office->id]);

                School::create([
                    'name' => $school['name'],
                    'testing_center_id' => $center->id,
                    'contact_person' => null,
                    'contact_number' => null,
                    'contact_email' => null,
                    'is_active' => true,
                ]);
            }
        }

        $this->shareTaclobanBetweenBothLeyteOffices();
        $this->designatePrimaryOffices();
    }

    /**
     * Leyte II serves the same testing center as Leyte I. Linking it here — not
     * in FieldOfficeSeeder — because the centers only exist once the schools
     * above have created them.
     */
    private function shareTaclobanBetweenBothLeyteOffices(): void
    {
        $leyteTwo = FieldOffice::where('code', 'LEY2')->first();
        $tacloban = TestingCenter::where('name', 'Tacloban City')->first();

        if ($leyteTwo && $tacloban) {
            $tacloban->fieldOffices()->syncWithoutDetaching([$leyteTwo->id]);
        }
    }

    /**
     * Every center needs exactly one office owning intake, since registration
     * resolves a member's field office from the center they choose. Only
     * Tacloban is actually contested; the rest each have one candidate.
     */
    private function designatePrimaryOffices(): void
    {
        foreach (TestingCenter::with('fieldOffices')->get() as $center) {
            if ($center->fieldOffices->isEmpty()
                || $center->fieldOffices->contains(fn ($office) => (bool) $office->pivot->is_primary)) {
                continue;
            }

            $center->fieldOffices()->updateExistingPivot(
                $center->fieldOffices->first()->id,
                ['is_primary' => true],
            );
        }
    }
}
