<?php

namespace Database\Factories;

use App\Models\FieldOffice;
use App\Models\TestingCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

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
     */
    public function forFieldOffice(FieldOffice|int $office): static
    {
        return $this->for(TestingCenter::factory()->forFieldOffice($office), 'testingCenter');
    }
}
