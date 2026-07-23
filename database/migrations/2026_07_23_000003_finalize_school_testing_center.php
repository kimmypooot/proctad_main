<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Testing center is now the school's authoritative home (backfilled in
        // the previous migration), so require it and retire the free-text city.
        //
        // The column was added with an ON DELETE SET NULL foreign key, which
        // MySQL refuses to keep on a NOT NULL column. Drop the FK first, tighten
        // the column, then re-add it as RESTRICT — a school must always have a
        // testing center, so a center in use cannot simply be deleted.
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['testing_center_id']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('testing_center_id')->nullable(false)->change();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreign('testing_center_id')->references('id')->on('testing_centers')->restrictOnDelete();
            $table->dropColumn('municipality');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['testing_center_id']);
            $table->string('municipality', 100)->default('')->after('name');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('testing_center_id')->nullable()->change();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreign('testing_center_id')->references('id')->on('testing_centers')->nullOnDelete();
        });
    }
};
