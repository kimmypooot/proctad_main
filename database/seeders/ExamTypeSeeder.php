<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Career Service Examination - Pen and Paper Test (Professional and Sub-Professional)',
            'Fire Officer Examination and Penology Officer Examination',
            'Career Service Examination - Computerized',
        ] as $name) {
            ExamType::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
