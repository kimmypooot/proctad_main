<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMIS is the only place staff are managed. A staff row here is a stub
     * that records who did what: `imis_user_id` ties it to the IMIS account,
     * and a field office's `imis_code` ties it to the IMIS organizational
     * unit that office corresponds to.
     */
    private const OFFICES = [
        'FOLI-TAC' => 'LFO-I',
        'FOLII-TAC' => 'LFO-II',
        'FOWL' => 'WLSO',
        'FOSL' => 'SLFO',
        'FOB' => 'BFO',
        'FOS' => 'SFO',
        'FOES' => 'ESFO',
        'FONS' => 'NSFO',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('imis_user_id')->nullable()->unique()->after('id');
        });

        Schema::table('field_offices', function (Blueprint $table) {
            $table->string('imis_code', 30)->nullable()->unique()->after('code');
        });

        foreach (self::OFFICES as $code => $imisCode) {
            DB::table('field_offices')->where('code', $code)->update(['imis_code' => $imisCode]);
        }
    }

    public function down(): void
    {
        Schema::table('field_offices', function (Blueprint $table) {
            $table->dropUnique(['imis_code']);
            $table->dropColumn('imis_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['imis_user_id']);
            $table->dropColumn('imis_user_id');
        });
    }
};
