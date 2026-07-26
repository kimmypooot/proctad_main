<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Other examination personnel are placed in a testing center, the way members
 * are: the center is the city they actually work in, and it is what field
 * office staff are scoped by. The office alone was too coarse — Leyte I and
 * Leyte II share Tacloban City, so an office told you who hired someone but not
 * where they serve.
 *
 * Regional-office personnel stay centerless: they serve region-wide, and
 * OtherExaminationPersonnel::scopeWithinJurisdictionOf admits them to every
 * office's pool on the strength of their office being regional.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('other_examination_personnel', 'testing_center_id')) {
            Schema::table('other_examination_personnel', function (Blueprint $table) {
                $table->foreignId('testing_center_id')
                    ->nullable()
                    ->after('field_office_id')
                    ->constrained('testing_centers')
                    ->nullOnDelete();
            });
        }

        // Place existing rows in the center their office handles. Where an
        // office handles several (Leyte I and Leyte II do not, but an office
        // may), prefer the one it administers, then the lowest id — deliberately
        // never leaving a non-regional row centerless, since that would hide it
        // from every field office exactly as the old null office did.
        $centerByOffice = DB::table('field_office_testing_center')
            ->orderByDesc('is_primary')
            ->orderBy('testing_center_id')
            ->get(['field_office_id', 'testing_center_id'])
            ->groupBy('field_office_id')
            ->map(fn ($rows) => $rows->first()->testing_center_id);

        $regionalOfficeIds = DB::table('field_offices')->where('is_regional', true)->pluck('id')->all();

        foreach ($centerByOffice as $officeId => $centerId) {
            if (in_array($officeId, $regionalOfficeIds)) {
                continue;
            }

            DB::table('other_examination_personnel')
                ->where('field_office_id', $officeId)
                ->whereNull('testing_center_id')
                ->update(['testing_center_id' => $centerId]);
        }
    }

    public function down(): void
    {
        Schema::table('other_examination_personnel', function (Blueprint $table) {
            $table->dropConstrainedForeignId('testing_center_id');
        });
    }
};
