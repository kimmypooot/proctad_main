<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_sessions', function (Blueprint $table) {
            $table->id();
            // The URL segment for /scan/{token}. Unguessable, so the link
            // itself is the credential — hence expires_at and revoked_at.
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->foreignId('examination_id')->nullable()->constrained('examinations')->cascadeOnDelete();
            $table->foreignId('training_id')->nullable()->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('examination_school_id')->nullable()->constrained('examination_school')->cascadeOnDelete();
            // Copied from the issuer so the public page keeps the same
            // field-office scoping the issuer would have had.
            $table->foreignId('field_office_id')->nullable()->constrained('field_offices');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_sessions');
    }
};
