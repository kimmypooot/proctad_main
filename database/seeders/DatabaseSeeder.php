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
    /**
     * Only the real CSC RO VIII baseline plus the staff accounts needed to log
     * in — no members, examinations, trainings or certificates. Those are
     * entered through the app.
     *
     * The demo seeders (MemberSeeder, ExaminationSeeder, TrainingSeeder,
     * OtherExaminationPersonnelSeeder, CertificateSeeder, DashboardDemoDataSeeder)
     * still exist and generate faked content for screenshots and manual testing;
     * run them by hand with `php artisan db:seed --class=MemberSeeder`. They are
     * kept out of the default run so `migrate:fresh --seed` always yields a
     * clean system rather than one that looks like it has history.
     */
    public function run(): void
    {
        $this->call([
            InitialDataSeeder::class,
            UserSeeder::class,
            EmailTemplateSeeder::class,
            SettingSeeder::class,
            FeeScheduleSeeder::class,
        ]);
    }
}
