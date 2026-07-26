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

    /** The regional office (RO8) — how "region-wide" is expressed, for members and OEP alike. */
    public function regional(): static
    {
        return $this->state(fn () => [
            'name' => 'CSC Regional Office VIII',
            'is_regional' => true,
        ]);
    }
}
