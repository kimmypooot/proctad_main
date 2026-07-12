<?php

namespace Database\Factories;

use App\Enums\ConfirmationAction;
use App\Models\ExamAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AssignmentConfirmation>
 */
class AssignmentConfirmationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_assignment_id' => ExamAssignment::factory(),
            'action' => fake()->randomElement(ConfirmationAction::cases()),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => null,
        ];
    }
}
