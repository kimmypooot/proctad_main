<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oep_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('other_examination_personnel_id')->constrained('other_examination_personnel')->cascadeOnDelete();
            $table->foreignId('examination_school_id')->constrained('examination_school')->cascadeOnDelete();
            $table->string('status', 20)->default('present');
            $table->string('scan_method', 10)->default('qr');
            $table->timestamp('scanned_at')->useCurrent();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['other_examination_personnel_id', 'examination_school_id'], 'oep_attendances_oep_venue_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oep_attendances');
    }
};
