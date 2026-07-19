<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Snapshot of the signature image in force at release, mirroring the
            // existing signatory_name/signatory_position snapshot. Without this,
            // replacing a signatory's image would retroactively re-sign every
            // certificate already issued — and regeneratePdf() overwrites the
            // stored PDF in place, so the change would reach members silently.
            $table->string('signatory_signature_path')->nullable()->after('signatory_position');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('signatory_signature_path');
        });
    }
};
