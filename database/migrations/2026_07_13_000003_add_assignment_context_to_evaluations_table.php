<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('examination_id')->nullable()->after('id')->constrained('examinations')->nullOnDelete();
            $table->foreignId('exam_assignment_id')->nullable()->after('examination_id')->constrained('exam_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('examination_id');
            $table->dropConstrainedForeignId('exam_assignment_id');
        });
    }
};
