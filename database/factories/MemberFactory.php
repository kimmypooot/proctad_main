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
            // Jurisdiction is decided by testing center, so a member without one
            // is invisible to the very staff who serve them — never what a test
            // setting only `field_office_id` intends. Resolved here rather than
            // in an afterCreating hook so the member is inserted once: a second
            // save would write a spurious "updated" audit entry.
            //
            // Declared after `field_office_id` deliberately: the factory expands
            // attributes in order, so the office is already a resolved id here.
            'testing_center_id' => function (array $attributes) {
                $office = FieldOffice::find($attributes['field_office_id']);

                if ($office === null || $office->is_regional) {
                    return null;
                }

                return $office->testingCenters()->value('testing_centers.id')
                    ?? TestingCenterFactory::new()->forFieldOffice($office)->create()->id;
            },
            'status' => MemberStatus::Active,
        ];
    }

    /** A regional-office member: serves region-wide, anchored to no center. */
    public function regional(): static
    {
        return $this->state([
            'field_office_id' => FieldOffice::factory()->state(['is_regional' => true]),
            'testing_center_id' => null,
        ]);
    }

    public function disqualified(): static
    {
        return $this->state([
            'status' => MemberStatus::Disqualified,
            'disqualification_remarks' => 'Sample disqualification record.',
        ]);
    }
}
