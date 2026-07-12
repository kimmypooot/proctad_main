<?php

namespace Database\Factories;

use App\Models\ExaminationSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExamRoom>
 */
class ExamRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'examination_school_id' => ExaminationSchool::factory(),
            'room_number' => 'Room-'.fake()->unique()->numerify('##'),
            'capacity' => fake()->numberBetween(20, 40),
            'designation' => fake()->optional()->randomElement(['Professional', 'Sub-Professional']),
        ];
    }
}
