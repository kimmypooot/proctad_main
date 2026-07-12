<?php

namespace Database\Seeders;

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use App\Models\FeeSchedule;
use Illuminate\Database\Seeder;

/**
 * Seeds one unconfigured (amount_cents = 0) row per ExamRole / PersonnelType so
 * every payee shows up in Fee Management immediately. Amounts are intentionally
 * left at 0 rather than seeded from any past exam's payroll figures — those are
 * historical data, not system defaults, and must be set by an administrator
 * before Payroll/Payroll Posting reports can be generated. Not run automatically
 * by DatabaseSeeder; run manually with `php artisan db:seed --class=FeeScheduleSeeder`.
 */
class FeeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ExamRole::cases() as $role) {
            FeeSchedule::updateOrCreate(
                ['payee_type' => PayeeType::ExamRole, 'payee_value' => $role->value],
                ['amount_cents' => 0],
            );
        }

        foreach (PersonnelType::cases() as $type) {
            FeeSchedule::updateOrCreate(
                ['payee_type' => PayeeType::PersonnelType, 'payee_value' => $type->value],
                ['amount_cents' => 0],
            );
        }
    }
}
