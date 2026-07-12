<?php

namespace Database\Factories;

use App\Enums\PersonnelType;
use App\Models\FieldOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NonExamPersonnel>
 */
class NonExamPersonnelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'sex' => fake()->randomElement(['male', 'female']),
            'contact_number' => '09'.fake()->numerify('#########'),
            'email' => fake()->optional()->safeEmail(),
            'agency' => fake()->optional()->company(),
            'position' => fake()->optional()->jobTitle(),
            'personnel_type' => fake()->randomElement(PersonnelType::cases()),
            'field_office_id' => FieldOffice::factory(),
            'is_active' => true,
        ];
    }
}
