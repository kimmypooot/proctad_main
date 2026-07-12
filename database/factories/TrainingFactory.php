<?php

namespace Database\Factories;

use App\Enums\TrainingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Training>
 */
class TrainingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->monthName().' '.fake()->year().' '.fake()->randomElement(['TEA Batch 1', 'Pre-Exam Briefing']),
            'type' => fake()->randomElement(TrainingType::cases()),
            'training_date' => fake()->dateTimeBetween('-6 months', '+3 months')->format('Y-m-d'),
            'venue' => fake()->randomElement(['CSC RO VIII Training Hall', 'Leyte Field Office', null]),
        ];
    }
}
