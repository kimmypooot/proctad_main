<?php

namespace Database\Factories;

use App\Enums\PersonnelType;
use App\Models\FieldOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OtherExaminationPersonnel>
 */
class OtherExaminationPersonnelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'sex' => fake()->randomElement(['male', 'female']),
            'contact_number' => '09'.fake()->numerify('#########'),
            'email' => fake()->optional()->safeEmail(),
            'agency' => fake()->optional()->company(),
            'position' => fake()->optional()->jobTitle(),
            'personnel_type' => fake()->randomElement(PersonnelType::cases()),
            'field_office_id' => FieldOffice::factory(),
            // Jurisdiction is decided by testing center, so personnel without
            // one are invisible to the very staff who serve them — never what a
            // test setting only `field_office_id` intends. Resolved inline (not
            // in afterCreating) so the row is inserted once, and declared after
            // the office so it is already a resolved id here. Mirrors
            // MemberFactory, which faces exactly the same problem.
            'testing_center_id' => function (array $attributes) {
                $office = FieldOffice::find($attributes['field_office_id']);

                if ($office === null || $office->is_regional) {
                    return null;
                }

                return $office->testingCenters()->value('testing_centers.id')
                    ?? TestingCenterFactory::new()->forFieldOffice($office)->create()->id;
            },
            'is_active' => true,
        ];
    }
}
