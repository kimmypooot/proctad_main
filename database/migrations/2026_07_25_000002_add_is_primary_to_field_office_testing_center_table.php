<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which office currently owns intake for a shared center. Only Tacloban
        // City is shared today (Leyte I and Leyte II take turns hosting), but
        // registration has to resolve to exactly one office, so every center
        // carries the flag and staff flip it when hosting rotates.
        Schema::table('field_office_testing_center', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('testing_center_id');
        });

        // Seed one primary per center: the lowest office id, which for Tacloban
        // is Leyte II (the office actually staffed today). Staff can flip it.
        foreach (DB::table('testing_centers')->pluck('id') as $centerId) {
            $officeId = DB::table('field_office_testing_center')
                ->where('testing_center_id', $centerId)
                ->orderBy('field_office_id')
                ->value('field_office_id');

            if ($officeId === null) {
                continue;
            }

            DB::table('field_office_testing_center')
                ->where('testing_center_id', $centerId)
                ->where('field_office_id', $officeId)
                ->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('field_office_testing_center', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
