<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many rooms one Supervising Examiner covers at this venue.
 *
 * StaffingRandomizer already accepted this as an argument, but nothing stored
 * it and no screen sent it — so the randomizer anchored supervisors every five
 * rooms while RoomStaffingCalculator independently assumed the same five. The
 * moment those two disagreed the grid would show supervisors against the wrong
 * rooms and over-count how many were required.
 *
 * Storing it on the venue makes the field office's choice the single source of
 * truth for both. Null means "use the designation's default", so venues staffed
 * before this existed keep behaving exactly as they did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_school', function (Blueprint $table) {
            $table->unsignedTinyInteger('rooms_per_supervisor')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('examination_school', function (Blueprint $table) {
            $table->dropColumn('rooms_per_supervisor');
        });
    }
};
