<?php

namespace Database\Factories;

use App\Models\FieldOffice;
use App\Models\TestingCenter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'testing_center_id' => TestingCenter::factory(),
            'name' => fake()->company().' National High School',
            'contact_person' => fake()->name(),
            'contact_number' => '09'.fake()->numerify('#########'),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }

    /**
     * Put the school under a testing center handled by the given field office.
     * Kept for the many tests that pin a school to an office; schools no longer
     * belong to an office directly, so this links through the testing center.
     *
     * Reuses the office's existing center rather than minting a new one each
     * call. Jurisdiction is decided by testing center, so a fresh center per
     * school would put two schools of the same office in different
     * jurisdictions — the opposite of what "for this field office" means.
     * Tests that want a second, distinct center pass `testing_center_id`.
     */
    public function forFieldOffice(FieldOffice|int $office): static
    {
        return $this->state(function () use ($office) {
            $officeId = $office instanceof FieldOffice ? $office->id : $office;

            $centerId = DB::table('field_office_testing_center')
                ->where('field_office_id', $officeId)
                ->value('testing_center_id')
                ?? TestingCenter::factory()->forFieldOffice($officeId)->create()->id;

            return ['testing_center_id' => $centerId];
        });
    }
}
