<?php

namespace Database\Factories;

use App\Models\ExaminationSchool;
use App\Models\OtherExaminationPersonnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OepAttendance>
 */
class OepAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'other_examination_personnel_id' => OtherExaminationPersonnel::factory(),
            'examination_school_id' => ExaminationSchool::factory(),
            'status' => 'present',
            'scan_method' => fake()->randomElement(['qr', 'manual']),
            'scanned_at' => now(),
        ];
    }
}
