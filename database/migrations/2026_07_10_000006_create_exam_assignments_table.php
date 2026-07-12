<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained('examinations')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('role', 40);
            $table->foreignId('field_office_id')->constrained('field_offices');
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->foreignId('attendance_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performance_rating', 30)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['examination_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
    }
};
