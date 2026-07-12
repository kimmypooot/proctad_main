<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignment_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')->constrained('exam_assignments')->cascadeOnDelete();
            $table->foreignId('examination_school_id')->constrained('examination_school')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['exam_assignment_id', 'examination_school_id'], 'exam_assignment_schools_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignment_schools');
    }
};
