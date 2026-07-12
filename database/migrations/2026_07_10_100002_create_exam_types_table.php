<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->foreignId('exam_type_id')->nullable()->after('type')
                ->constrained('exam_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_type_id');
        });

        Schema::dropIfExists('exam_types');
    }
};
