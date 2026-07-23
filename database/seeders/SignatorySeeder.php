<?php

namespace Database\Seeders;

use App\Models\FieldOffice;
use App\Models\Signatory;
use Illuminate\Database\Seeder;

class SignatorySeeder extends Seeder
{
    public function run(): void
    {
        if (Signatory::exists()) {
            return;
        }

        // Region-wide default used when a Field Office has no signatory of its own.
        Signatory::create([
            'field_office_id' => null,
            'name' => 'Atty. Marilyn E. Taldo',
            'position' => 'Director IV',
            'active' => true,
        ]);

        $leyte = FieldOffice::where('code', 'LEY')->first();

        if ($leyte) {
            Signatory::create([
                'field_office_id' => $leyte->id,
                'name' => 'Atty. Flordeliza C. Algas',
                'position' => 'Director III',
                'active' => true,
            ]);
        }
    }
}
