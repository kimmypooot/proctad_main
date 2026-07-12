<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_examination_personnel', function (Blueprint $table) {
            $table->id();
            $table->string('oep_id', 30)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('sex', 10);
            $table->string('contact_number', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('agency')->nullable();
            $table->string('position')->nullable();
            $table->string('personnel_type', 30)->index();
            $table->foreignId('field_office_id')->nullable()->constrained('field_offices')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_examination_personnel');
    }
};
