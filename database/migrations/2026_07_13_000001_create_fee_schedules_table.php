<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('payee_type');
            $table->string('payee_value');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['payee_type', 'payee_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};
