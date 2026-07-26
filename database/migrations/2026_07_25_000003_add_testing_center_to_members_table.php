<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member's jurisdiction anchor. Field office stays as the
        // administrative owner, but who may see and manage a member is now
        // decided by the testing center they serve — which is what lets Leyte I
        // and Leyte II staff share the Tacloban City roster.
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('testing_center_id')
                ->nullable()
                ->after('field_office_id')
                ->constrained('testing_centers')
                ->restrictOnDelete();
        });

        // Backfill from each member's office. The center -> office direction is
        // one-to-one, but office -> center is not: Samar handles both Calbayog
        // City and Catbalogan City, so its members cannot be resolved here and
        // are left null for staff to set. Regional-office members stay null by
        // design — field_offices.is_regional is what marks them.
        $singleCenterOffices = DB::table('field_office_testing_center')
            ->select('field_office_id', DB::raw('MIN(testing_center_id) as testing_center_id'))
            ->groupBy('field_office_id')
            ->havingRaw('COUNT(*) = 1')
            ->pluck('testing_center_id', 'field_office_id');

        foreach ($singleCenterOffices as $officeId => $centerId) {
            DB::table('members')
                ->where('field_office_id', $officeId)
                ->update(['testing_center_id' => $centerId]);
        }

        $unresolved = DB::table('members')
            ->join('field_offices', 'members.field_office_id', '=', 'field_offices.id')
            ->whereNull('members.testing_center_id')
            ->where('field_offices.is_regional', false)
            ->pluck('members.proctad_id');

        if ($unresolved->isNotEmpty()) {
            // Surfaced in the Members UI too, but log it so whoever runs the
            // migration has the worklist in front of them.
            logger()->warning('Members needing a testing center assigned manually: '.$unresolved->implode(', '));
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('testing_center_id');
        });
    }
};
