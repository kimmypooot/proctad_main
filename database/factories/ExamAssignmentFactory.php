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
            'field_office_id' => fn (array $attributes) => Member::find($attributes['member_id'])?->field_office_id
                ?? \App\Models\FieldOffice::factory(),
        ];
    }
}
