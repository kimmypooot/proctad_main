<?php

namespace Database\Factories;

use App\Models\ExaminationSchool;
use App\Models\NonExamPersonnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NepAttendance>
 */
class NepAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'non_exam_personnel_id' => NonExamPersonnel::factory(),
            'examination_school_id' => ExaminationSchool::factory(),
            'status' => 'present',
            'scan_method' => fake()->randomElement(['qr', 'manual']),
            'scanned_at' => now(),
        ];
    }
}
