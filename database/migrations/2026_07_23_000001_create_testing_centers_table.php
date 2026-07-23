<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testing_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_office_id')->constrained('field_offices')->cascadeOnDelete();
            $table->string('name'); // the testing-center city, e.g. "Tacloban City"
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // A city name is unique within a field office.
            $table->unique(['field_office_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_centers');
    }
};
