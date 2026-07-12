<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('respondent_name');
            $table->string('designation', 30);
            $table->foreignId('field_office_id')->nullable()->constrained('field_offices')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();

            // Supervising Examiner: one entry per Room Examiner/Proctor rated.
            $table->json('room_ratings')->nullable();

            // Room Readiness checklist — Room Examiner/Proctor and Supervising Examiner.
            $table->json('room_readiness')->nullable();

            // Exam Preparation ratings — Room Examiner/Proctor only.
            $table->json('exam_preparation')->nullable();

            // Examination Administration section — Chief Examiner and Supervising Examiner.
            $table->json('venue_readiness')->nullable();
            $table->text('venue_comment')->nullable();
            $table->json('committee_coordination')->nullable();
            $table->text('committee_comment')->nullable();
            $table->json('conduct_of_exam')->nullable();
            $table->text('conduct_comment')->nullable();
            $table->json('examinee_experience')->nullable();
            $table->text('examinee_comment')->nullable();

            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->text('what_worked')->nullable();
            $table->text('challenges')->nullable();
            $table->text('improvements')->nullable();
            $table->text('suggestions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
