<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail of assignment confirmation events.
        Schema::create('assignment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')->constrained('exam_assignments')->cascadeOnDelete();
            $table->string('action', 30)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_confirmations');
    }
};
