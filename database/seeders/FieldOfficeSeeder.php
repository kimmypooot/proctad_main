<?php

namespace Database\Seeders;

use App\Models\FieldOffice;
use Illuminate\Database\Seeder;

class FieldOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            ['code' => 'RO8', 'name' => 'CSC Regional Office VIII', 'address' => 'Government Center, Candahug, Palo, Leyte'],
            ['code' => 'LEY', 'name' => 'Leyte Field Office', 'address' => 'Tacloban City, Leyte'],
            ['code' => 'SLE', 'name' => 'Southern Leyte Field Office', 'address' => 'Maasin City, Southern Leyte'],
            ['code' => 'BIL', 'name' => 'Biliran Field Office', 'address' => 'Naval, Biliran'],
            ['code' => 'SAM', 'name' => 'Samar Field Office', 'address' => 'Catbalogan City, Samar'],
            ['code' => 'ESA', 'name' => 'Eastern Samar Field Office', 'address' => 'Borongan City, Eastern Samar'],
            ['code' => 'NSA', 'name' => 'Northern Samar Field Office', 'address' => 'Catarman, Northern Samar'],
        ];

        foreach ($offices as $office) {
            FieldOffice::updateOrCreate(['code' => $office['code']], $office);
        }
    }
}
