<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trainings run as half-day sittings — an AM and a PM batch of the same
     * course when the head count needs splitting — and only rarely as a whole
     * day. That was unrepresentable: every sitting collapsed into one row.
     *
     * end_date goes at the same time. It modelled multi-day trainings, which
     * do not happen; leaving an optional date field on the form only invites
     * data that nothing reads.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('session', 12)->default('whole_day')->after('training_date');
        });

        // Existing rows predate the split and were recorded as a single
        // sitting, which is what the column default already says — no backfill
        // to do. The few that carried a real end_date lose the span; note it in
        // the title so the fact is not dropped silently. Done row by row in PHP
        // rather than one UPDATE, so it runs on sqlite as well as MySQL.
        DB::table('trainings')
            ->whereNotNull('end_date')
            ->whereColumn('end_date', '>', 'training_date')
            ->get(['id', 'title', 'training_date', 'end_date'])
            ->each(fn ($row) => DB::table('trainings')
                ->where('id', $row->id)
                ->update(['title' => "{$row->title} ({$row->training_date} to {$row->end_date})"]));

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('training_date');
            $table->dropColumn('session');
        });
    }
};
