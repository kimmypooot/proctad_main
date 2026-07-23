<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ownership now lives in field_office_testing_center; retire the single
        // owner column (and the per-owner name uniqueness that came with it).
        //
        // Order matters: the foreign key relies on the composite unique index,
        // so MySQL refuses to drop the index while the FK still exists. Drop the
        // FK first, then the unique, then the column.
        Schema::table('testing_centers', function (Blueprint $table) {
            $table->dropForeign(['field_office_id']);
            $table->dropUnique(['field_office_id', 'name']);
            $table->dropColumn('field_office_id');
        });
    }

    public function down(): void
    {
        Schema::table('testing_centers', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable()->after('id')->constrained('field_offices')->cascadeOnDelete();
            $table->unique(['field_office_id', 'name']);
        });
    }
};
