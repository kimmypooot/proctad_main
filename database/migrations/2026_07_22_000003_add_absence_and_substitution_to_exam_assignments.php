<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an Alternate Examiner take over a seat whose assignee did not report on
 * exam day.
 *
 * Two facts the schema could not previously express:
 *
 * 1. Absence. Attendance was binary — `attendance_confirmed_at` set or null —
 *    so someone who never arrived looked exactly like someone not yet scanned.
 *    That ambiguity is the whole problem on exam morning, because "not here"
 *    versus "not here yet" is precisely the call that triggers the alternate.
 *
 * 2. Substitution. An alternate who covered a room was still recorded as
 *    'alternate_examiner', so their certificate misstated their service and
 *    they fell outside ExamRole::evaluableCases() — the room they actually ran
 *    produced no evaluation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->timestamp('marked_absent_at')->nullable()->after('attendance_confirmed_by');
            $table->foreignId('marked_absent_by')->nullable()->after('marked_absent_at')
                ->constrained('users')->nullOnDelete();

            // The seat this assignment was called in to cover. Unique: a vacant
            // seat is filled by one alternate, and without the constraint a
            // double-activation would leave two people holding one room with
            // nothing to say which is authoritative.
            $table->foreignId('covering_for_assignment_id')->nullable()->after('marked_absent_by')
                ->constrained('exam_assignments')->nullOnDelete();
            $table->unique('covering_for_assignment_id');

            // What they were assigned as before stepping in — always
            // 'alternate_examiner' today, but stored rather than assumed so the
            // substitution can be undone and so reports can still tell that
            // this person served as a reserve.
            $table->string('original_role', 40)->nullable()->after('covering_for_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropUnique(['covering_for_assignment_id']);
            $table->dropConstrainedForeignId('covering_for_assignment_id');
            $table->dropConstrainedForeignId('marked_absent_by');
            $table->dropColumn(['marked_absent_at', 'original_role']);
        });
    }
};
