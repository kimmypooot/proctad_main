<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_school_id')->constrained('examination_school')->cascadeOnDelete();
            $table->string('room_number', 50);
            $table->unsignedSmallInteger('capacity');
            $table->string('designation', 100)->nullable();
            $table->timestamps();
            $table->unique(['examination_school_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rooms');
    }
};
