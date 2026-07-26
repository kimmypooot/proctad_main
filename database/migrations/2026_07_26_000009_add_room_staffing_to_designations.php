<?php

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Services\RoomStaffingCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the per-room staffing grid data-driven.
 *
 * `rooms_per_slot` is how many rooms one person in this designation covers:
 * 1 means one per room, 5 means one per group of five (anchored at the group's
 * first room, matching StaffingRandomizer). Null means the designation takes no
 * part in room staffing and does not appear in the grid at all.
 *
 * Seeded with the three values that were hardcoded in RoomStaffingCalculator,
 * so the grid opens describing exactly what it already did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->unsignedSmallInteger('rooms_per_slot')->nullable()->after('is_active');
        });

        // Ordered as the grid has always shown them (Proctor, Room Examiner,
        // Supervising Examiner) rather than in enum-declaration order, since
        // the columns now follow sort_order.
        $defaults = [
            ExamRole::Proctor->value => 1,
            ExamRole::RoomExaminer->value => 1,
            ExamRole::SupervisingExaminer->value => RoomStaffingCalculator::ROOMS_PER_SUPERVISOR,
        ];

        $sort = 0;

        foreach ($defaults as $key => $roomsPerSlot) {
            DB::table('designations')
                ->where('section', PayeeType::ExamRole->value)
                ->where('key', $key)
                ->update(['rooms_per_slot' => $roomsPerSlot, 'sort_order' => $sort++]);
        }
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('rooms_per_slot');
        });
    }
};
