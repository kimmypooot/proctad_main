<?php

namespace Database\Factories;

use App\Enums\ExamRole;
use App\Models\Examination;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExamAssignment>
 */
class ExamAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'examination_id' => Examination::factory(),
            'member_id' => Member::factory(),
            'role' => fake()->randomElement(ExamRole::cases()),
            // Both copied from the member. The center is what field office staff
            // are scoped by, so an assignment without one is invisible to them;
            // the office is only set for members who are also CSC staff.
            'field_office_id' => fn (array $attributes) => Member::find($attributes['member_id'])?->field_office_id,
            'testing_center_id' => fn (array $attributes) => Member::find($attributes['member_id'])?->testing_center_id,
        ];
    }
}
