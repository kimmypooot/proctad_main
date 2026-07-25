<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marks the regional office (RO8), whose members serve region-wide
        // rather than out of one testing center. A flag rather than a check on
        // `code`, so a future reorganisation is a data change, not a code one.
        Schema::table('field_offices', function (Blueprint $table) {
            $table->boolean('is_regional')->default(false)->after('is_active');
        });

        DB::table('field_offices')->where('code', 'RO8')->update(['is_regional' => true]);
    }

    public function down(): void
    {
        Schema::table('field_offices', function (Blueprint $table) {
            $table->dropColumn('is_regional');
        });
    }
};
