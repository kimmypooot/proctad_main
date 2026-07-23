<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff are linked directly to the testing centers they operate at.
        // A user's field office handles several centers (field_office_testing_center),
        // and a user can work across more than one, so this is a many-to-many.
        Schema::create('testing_center_user', function (Blueprint $table) {
            $table->foreignId('testing_center_id')->constrained('testing_centers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['testing_center_id', 'user_id']);
        });

        // Backfill: connect each current user to every testing center their
        // field office currently handles — the only unambiguous link the
        // existing data affords (a user still carries a single field_office_id).
        $centersByOffice = DB::table('field_office_testing_center')
            ->get(['field_office_id', 'testing_center_id'])
            ->groupBy('field_office_id');

        $now = now();
        $rows = [];

        foreach (DB::table('users')->whereNotNull('field_office_id')->get(['id', 'field_office_id']) as $user) {
            foreach ($centersByOffice->get($user->field_office_id, collect()) as $link) {
                $rows[] = [
                    'user_id' => $user->id,
                    'testing_center_id' => $link->testing_center_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows) {
            DB::table('testing_center_user')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_center_user');
    }
};
