<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One assignment, one evaluation.
 *
 * The public form accepted any existing assignment id with no ownership check
 * and no uniqueness, so the same assignment could be evaluated repeatedly — by
 * the respondent double-clicking, or by anyone at all submitting in their name.
 * The application now refuses both (EvaluationController::store), and this is
 * the constraint that makes it true even under a race.
 *
 * NOTE FOR THE OPERATOR: if duplicates already exist in production, the index
 * cannot be created while they do. This migration keeps the earliest row per
 * assignment and removes the later ones — the first submission is the one the
 * respondent actually made, and anything after it is a double-submit or a
 * forgery. Take a database backup and review the reported count before running
 * this against live data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicates();

        Schema::table('evaluations', function (Blueprint $table) {
            $table->unique('exam_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique(['exam_assignment_id']);
        });
    }

    private function removeDuplicates(): void
    {
        $keep = DB::table('evaluations')
            ->selectRaw('MIN(id) as id')
            ->whereNotNull('exam_assignment_id')
            ->groupBy('exam_assignment_id')
            ->pluck('id');

        $doomed = DB::table('evaluations')
            ->whereNotNull('exam_assignment_id')
            ->whereNotIn('id', $keep)
            ->pluck('id');

        if ($doomed->isEmpty()) {
            return;
        }

        // Said out loud rather than done silently: this deletes records a
        // person submitted, and whoever runs the migration should see it.
        echo "  Removing {$doomed->count()} duplicate evaluation row(s), keeping the earliest per assignment.".PHP_EOL;

        DB::table('evaluations')->whereIn('id', $doomed)->delete();
    }
};
