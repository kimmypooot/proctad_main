<?php

namespace Database\Factories;

use App\Models\FieldOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'field_office_id' => FieldOffice::factory(),
            'name' => fake()->company().' National High School',
            'municipality' => fake()->city(),
            'contact_person' => fake()->name(),
            'contact_number' => '09'.fake()->numerify('#########'),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}
