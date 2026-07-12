<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('default_member_status', 'active', 'string');
        Setting::set('proctad_id_prefix', 'PROCTAD-CSCRO8-', 'string');
        Setting::set('assignment_confirmation_expiry_days', 7, 'number');
        Setting::set('assignment_reminder_after_days', 3, 'number');
        Setting::set('site_maintenance_mode', false, 'boolean');
    }
}
