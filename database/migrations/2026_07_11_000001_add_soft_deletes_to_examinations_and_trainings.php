<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting an examination or training cascades to wipe every linked
     * exam_assignment / training_assignment (service history, certificate
     * trail via the certifiable morph). Soft deletes add a recovery window
     * for an accidental delete without changing any existing behavior for
     * intentional deletes (still hidden from normal queries).
     */
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
