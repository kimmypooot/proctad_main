<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-designation overrides: a custom name, and whether the designation is
 * still in use.
 *
 * Designations themselves stay in code (App\Enums\ExamRole and
 * App\Enums\PersonnelType) because the staffing, payroll and evaluation rules
 * name specific ones — see DesignationRegistry. Only rows for designations an
 * administrator has actually changed are stored; anything absent falls back to
 * the enum's own label and counts as active.
 *
 * Deactivating never deletes: historical assignments keep pointing at the
 * value, so past examinations, payrolls and service records read exactly as
 * they did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designation_settings', function (Blueprint $table) {
            $table->id();
            // 'exam_role' or 'personnel_type' — mirrors PayeeType, so a row
            // lines up one-to-one with its fee_schedules counterpart.
            $table->string('type', 20);
            $table->string('value', 64);
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_settings');
    }
};
