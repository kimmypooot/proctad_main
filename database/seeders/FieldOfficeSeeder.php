<?php

namespace Database\Seeders;

use App\Models\FieldOffice;
use Illuminate\Database\Seeder;

class FieldOfficeSeeder extends Seeder
{
    public function run(): void
    {
        // Leyte is two offices, not one: Leyte I and Leyte II have separate
        // staff and take turns hosting, but share one jurisdiction and one
        // testing center (Tacloban City — linked by SchoolSeeder, which is
        // where the centers are created). The regional office is flagged rather
        // than matched on its code, since its members serve region-wide.
        $offices = [
            ['code' => 'RO8', 'name' => 'CSC Regional Office VIII', 'address' => 'Government Center, Candahug, Palo, Leyte', 'is_regional' => true],
            ['code' => 'LEY', 'name' => 'CSC Field Office - Leyte I', 'address' => 'Tacloban City, Leyte'],
            ['code' => 'LEY2', 'name' => 'CSC Field Office - Leyte II', 'address' => 'Tacloban City, Leyte'],
            ['code' => 'SLE', 'name' => 'CSC Field Office - Southern Leyte', 'address' => 'Maasin City, Southern Leyte'],
            ['code' => 'BIL', 'name' => 'CSC Field Office - Biliran', 'address' => 'Naval, Biliran'],
            ['code' => 'SAM', 'name' => 'CSC Field Office - Samar', 'address' => 'Catbalogan City, Samar'],
            ['code' => 'ESA', 'name' => 'CSC Field Office - Eastern Samar', 'address' => 'Borongan City, Eastern Samar'],
            ['code' => 'NSA', 'name' => 'CSC Field Office - Northern Samar', 'address' => 'Catarman, Northern Samar'],
        ];

        foreach ($offices as $office) {
            FieldOffice::updateOrCreate(['code' => $office['code']], $office);
        }
    }
}
