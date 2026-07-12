<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy data legitimately reuses a room number within a venue for
        // different designations (e.g. Professional vs Sub-Professional
        // sessions), so room-number uniqueness cannot be per-venue only.
        // The FK on examination_school_id is backed by the unique index, so a
        // replacement index must exist before the unique one can be dropped.
        Schema::table('exam_rooms', function (Blueprint $table) {
            $table->index('examination_school_id');
        });

        Schema::table('exam_rooms', function (Blueprint $table) {
            $table->dropUnique(['examination_school_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_rooms', function (Blueprint $table) {
            $table->unique(['examination_school_id', 'room_number']);
        });

        Schema::table('exam_rooms', function (Blueprint $table) {
            $table->dropIndex(['examination_school_id']);
        });
    }
};
