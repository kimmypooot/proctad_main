<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('testing_center_id')
                ->nullable()
                ->after('field_office_id')
                ->constrained('testing_centers')
                ->nullOnDelete();
        });

        // Promote each school's existing free-text municipality into a real
        // Testing Center under its field office, then point the school at it.
        // Grouping is per (field_office_id, municipality) so the same city in
        // the same office collapses to one center.
        $schools = DB::table('schools')->get(['id', 'field_office_id', 'municipality']);

        $centers = []; // "fieldOfficeId|city" => testing_center_id

        foreach ($schools as $school) {
            $city = trim((string) $school->municipality) ?: 'Unspecified';
            $key = $school->field_office_id.'|'.$city;

            if (! isset($centers[$key])) {
                $centers[$key] = DB::table('testing_centers')->insertGetId([
                    'field_office_id' => $school->field_office_id,
                    'name' => $city,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('schools')
                ->where('id', $school->id)
                ->update(['testing_center_id' => $centers[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('testing_center_id');
        });
    }
};
