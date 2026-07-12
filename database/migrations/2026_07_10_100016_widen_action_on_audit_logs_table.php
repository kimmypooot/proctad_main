<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy security-log event types imported by proctad:migrate-legacy
        // exceed the original 20-character limit.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 20)->change();
        });
    }
};
