<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Examination>
 */
class ExaminationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->monthName().' '.fake()->year().' CSE-PPT',
            'type' => fake()->randomElement(['CSE-PPT Professional', 'CSE-PPT Sub-Professional']),
            'exam_date' => fake()->dateTimeBetween('-1 year', '+6 months')->format('Y-m-d'),
        ];
    }
}
