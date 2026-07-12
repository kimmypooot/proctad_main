<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignment_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')
                ->constrained('exam_assignments')->cascadeOnDelete();
            $table->unsignedBigInteger('examination_school_id');
            $table->foreign('examination_school_id', 'eaa_examination_school_id_foreign')
                ->references('id')->on('examination_school')->cascadeOnDelete();
            $table->string('status', 20)->default('present');
            $table->string('scan_method', 10)->default('qr');
            $table->timestamp('scanned_at')->useCurrent();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['exam_assignment_id', 'examination_school_id'], 'exam_assignment_attendances_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignment_attendances');
    }
};
