<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FieldOffice>
 */
class FieldOfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Field Office',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'address' => fake()->address(),
        ];
    }
}
