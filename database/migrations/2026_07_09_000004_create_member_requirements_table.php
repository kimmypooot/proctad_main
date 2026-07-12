<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('requirement', 50);
            $table->boolean('complied')->default(false);
            $table->string('file_path')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'requirement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_requirements');
    }
};
