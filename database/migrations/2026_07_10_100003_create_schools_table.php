<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_office_id')->constrained('field_offices')->cascadeOnDelete();
            $table->string('name');
            $table->string('municipality', 100);
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('contact_email', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
