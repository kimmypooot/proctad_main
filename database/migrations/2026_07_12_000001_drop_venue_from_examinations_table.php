<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the free-text `venue` field, which was never linked to the real
 * `examination_school` (School) attachments made in the Venues & Rooms step —
 * the two could silently disagree about where the exam was actually held.
 * The attached School records are now the single source of truth for venue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn('venue');
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->string('venue')->nullable();
        });
    }
};
