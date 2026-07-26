<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TrainingAssignment>
 */
class TrainingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'training_id' => Training::factory(),
            'member_id' => Member::factory(),
            // Both copied from the member — see ExamAssignmentFactory.
            'field_office_id' => fn (array $attributes) => Member::find($attributes['member_id'])?->field_office_id,
            'testing_center_id' => fn (array $attributes) => Member::find($attributes['member_id'])?->testing_center_id,
        ];
    }
}
