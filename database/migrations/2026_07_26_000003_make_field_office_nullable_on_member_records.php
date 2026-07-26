<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The same four tables that gained a testing center in the previous migration. */
    private const TABLES = ['exam_assignments', 'blacklists', 'training_assignments', 'certificates'];

    public function up(): void
    {
        // These copy the member's field office when written, and external test
        // administrators no longer have one — an office says who a CSC employee
        // works for. The column stays for records about members who are staff,
        // and testing_center_id now carries the jurisdiction for everyone.
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('field_office_id')->nullable()->change();
            });
        }

        // Existing rows copied an office from members who have since been
        // detached from theirs. Leaving those in place would keep field office
        // staff seeing records by office for some members and by center for
        // others; clear them so there is one rule.
        foreach (self::TABLES as $table) {
            DB::table($table)
                ->whereNotNull('field_office_id')
                ->whereExists(fn ($sub) => $sub
                    ->select(DB::raw(1))
                    ->from('members')
                    ->whereColumn('members.id', "{$table}.member_id")
                    ->whereNull('members.field_office_id'))
                ->update(['field_office_id' => null]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('field_office_id')->update(['field_office_id' => 1]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('field_office_id')->nullable(false)->change();
            });
        }
    }
};
