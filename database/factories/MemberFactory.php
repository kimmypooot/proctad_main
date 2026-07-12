<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\FieldOffice;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Set explicitly (not left to the model's creating() hook): the
            // DatabaseSeeder runs under WithoutModelEvents, which suppresses
            // that hook, and proctad_id has no DB-level default.
            'proctad_id' => Member::generateProctadId(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'sex' => fake()->randomElement(['male', 'female']),
            'email' => fake()->unique()->safeEmail(),
            'mobile_number' => '09'.fake()->numerify('#########'),
            'agency' => fake()->randomElement([
                'DepEd Division Office', 'DOH Regional Office VIII', 'DILG Regional Office VIII',
                'LGU Tacloban City', 'DPWH Regional Office VIII', 'BIR Revenue Region 14',
            ]),
            'position' => fake()->randomElement(['Administrative Officer II', 'Teacher III', 'Nurse II', 'Engineer I', null]),
            'field_office_id' => FieldOffice::factory(),
            'status' => MemberStatus::Active,
        ];
    }

    public function disqualified(): static
    {
        return $this->state([
            'status' => MemberStatus::Disqualified,
            'disqualification_remarks' => 'Sample disqualification record.',
        ]);
    }
}
