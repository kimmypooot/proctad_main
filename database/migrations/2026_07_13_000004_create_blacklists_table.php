<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('field_office_id')->constrained('field_offices');
            $table->text('reason');
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('blacklisted_by')->constrained('users');
            $table->timestamp('blacklisted_at');
            $table->foreignId('lifted_by')->nullable()->constrained('users');
            $table->timestamp('lifted_at')->nullable();
            $table->text('lift_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklists');
    }
};
