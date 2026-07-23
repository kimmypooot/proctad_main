<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A testing center can now be handled by several field offices (e.g.
        // Leyte I and Leyte II taking turns hosting at Tacloban City), so the
        // ownership moves from a column on testing_centers to this pivot.
        Schema::create('field_office_testing_center', function (Blueprint $table) {
            $table->foreignId('field_office_id')->constrained('field_offices')->cascadeOnDelete();
            $table->foreignId('testing_center_id')->constrained('testing_centers')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['field_office_id', 'testing_center_id']);
        });

        // Seed the pivot from each center's current single owner.
        foreach (DB::table('testing_centers')->get(['id', 'field_office_id']) as $center) {
            if ($center->field_office_id === null) {
                continue;
            }

            DB::table('field_office_testing_center')->insertOrIgnore([
                'field_office_id' => $center->field_office_id,
                'testing_center_id' => $center->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_office_testing_center');
    }
};
