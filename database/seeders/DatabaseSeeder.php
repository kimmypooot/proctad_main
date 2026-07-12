<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FieldOfficeSeeder::class,
            UserSeeder::class,
            MemberSeeder::class,
            SignatorySeeder::class,
            ExamTypeSeeder::class,
            SchoolSeeder::class,
            ExaminationSeeder::class,
            TrainingSeeder::class,
            NonExamPersonnelSeeder::class,
            CertificateSeeder::class,
            EmailTemplateSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
