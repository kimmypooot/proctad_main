<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records about a member, which copied the member's field office when they
     * were created. External members no longer have one, so these carry the
     * testing center instead — the same basis staff are now scoped by.
     *
     * scanner_sessions is deliberately absent: it stamps the field office of
     * the staff member who issued the link, not of any test administrator, so
     * it keeps working unchanged.
     */
    private const TABLES = ['exam_assignments', 'blacklists', 'training_assignments', 'certificates'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'testing_center_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('testing_center_id')
                        ->nullable()
                        ->after('field_office_id')
                        ->constrained('testing_centers')
                        ->nullOnDelete();
                });
            }

            // Backfill from the member the record is about. Members whose own
            // center is null (CSC staff, who are anchored by office instead)
            // leave a null here too, which is correct: those records are found
            // through the office, not the center.
            //
            // A correlated subquery rather than an UPDATE ... JOIN: the join
            // form is MySQL-only and the test suite runs on SQLite. Written as
            // raw SQL because the query builder leaves the joined table
            // unprefixed, which this connection's `proctad_` prefix then fails
            // to resolve — so the prefix is applied by hand.
            $prefix = DB::getTablePrefix();

            DB::statement(
                "UPDATE {$prefix}{$table}
                 SET testing_center_id = (
                     SELECT m.testing_center_id
                     FROM {$prefix}members m
                     WHERE m.id = {$prefix}{$table}.member_id
                 )"
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('testing_center_id');
            });
        }
    }
};
