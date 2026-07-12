<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $leyte = FieldOffice::where('code', 'LEY')->first();

        $accounts = [
            ['role' => UserRole::SuperAdmin, 'first_name' => 'Super', 'last_name' => 'Administrator', 'email' => 'superadmin@proctad.test', 'field_office_id' => null],
            ['role' => UserRole::EsdAdmin, 'first_name' => 'ESD', 'last_name' => 'Administrator', 'email' => 'esdadmin@proctad.test', 'field_office_id' => null],
            ['role' => UserRole::Management, 'first_name' => 'Regional', 'last_name' => 'Director', 'email' => 'management@proctad.test', 'field_office_id' => null],
            ['role' => UserRole::FieldDirector, 'first_name' => 'Field', 'last_name' => 'Director', 'email' => 'director@proctad.test', 'field_office_id' => $leyte?->id],
            ['role' => UserRole::FoAdmin, 'first_name' => 'Testing Center', 'last_name' => 'Admin', 'email' => 'foadmin@proctad.test', 'field_office_id' => $leyte?->id],
            ['role' => UserRole::Member, 'first_name' => 'Proctad', 'last_name' => 'Member', 'email' => 'member@proctad.test', 'field_office_id' => $leyte?->id],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(['email' => $account['email']], [
                ...$account,
                'name' => trim($account['first_name'].' '.$account['last_name']),
                'mobile_number' => '09170000000',
                'password' => 'password',
            ]);
        }
    }
}
