<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Other examination personnel must belong to a field office.
 *
 * The column was nullable because legacy `proctad_non_exam_personnel` documented
 * "NULL = region-wide" — but nothing in this app ever implemented that reading.
 * Every jurisdiction check compared the office against the user's own, and
 * `in_array(null, [...], true)` is false, so a null-office record was invisible
 * to every field office rather than visible to all of them. Every legacy row
 * carried NULL, so the whole imported roster was unreachable for FO staff.
 *
 * Region-wide is now expressed the same way it is for members: the regional
 * office (field_offices.is_regional), which
 * OtherExaminationPersonnel::scopeWithinJurisdictionOf admits into every
 * office's pool. This backfills the old nulls to that office, preserving the
 * intent legacy wrote down, and closes the column so the ambiguity cannot
 * return.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('other_examination_personnel')->whereNull('field_office_id')->count();

        if ($orphans > 0) {
            $regionalOfficeId = DB::table('field_offices')->where('is_regional', true)->value('id');

            if ($regionalOfficeId === null) {
                throw new RuntimeException(
                    "Cannot backfill {$orphans} other examination personnel with no field office: "
                    .'no regional office exists. Run `php artisan db:seed --class=FieldOfficeSeeder` first.'
                );
            }

            DB::table('other_examination_personnel')
                ->whereNull('field_office_id')
                ->update(['field_office_id' => $regionalOfficeId]);
        }

        // The old foreign key was ON DELETE SET NULL, which MySQL rejects once
        // the column is NOT NULL, so it is rebuilt as a restricting key —
        // matching members.field_office_id, where deleting an office in use is
        // likewise refused rather than silently orphaning people.
        Schema::table('other_examination_personnel', function (Blueprint $table) {
            $table->dropForeign(['field_office_id']);
        });

        Schema::table('other_examination_personnel', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable(false)->change();
            $table->foreign('field_office_id')->references('id')->on('field_offices');
        });
    }

    public function down(): void
    {
        Schema::table('other_examination_personnel', function (Blueprint $table) {
            $table->dropForeign(['field_office_id']);
        });

        Schema::table('other_examination_personnel', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable()->change();
            $table->foreign('field_office_id')->references('id')->on('field_offices')->nullOnDelete();
        });
    }
};
