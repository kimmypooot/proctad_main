<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExaminationSchool>
 */
class ExaminationSchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'examination_id' => Examination::factory(),
            'school_id' => School::factory(),
            'is_active' => true,
        ];
    }
}
