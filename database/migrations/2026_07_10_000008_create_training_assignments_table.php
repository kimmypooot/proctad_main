<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('field_office_id')->constrained('field_offices');
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->foreignId('attendance_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['training_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assignments');
    }
};
