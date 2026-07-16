<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_office_id');
        });
    }
};
