<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_no', 40)->nullable()->unique();
            $table->string('type', 30);
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('field_office_id')->constrained('field_offices');
            $table->morphs('certifiable');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('disapproval_remarks')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_position')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['type', 'certifiable_type', 'certifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
