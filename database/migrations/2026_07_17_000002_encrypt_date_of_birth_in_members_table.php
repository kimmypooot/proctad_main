<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * date_of_birth is PII (RA 10173 Data Privacy Act) — store it encrypted at
 * rest via the Member model's `encrypted` cast. Ciphertext no longer fits a
 * `date` column, so it's swapped for `text`. No production data exists yet
 * for this column, so a plain drop+recreate is safe (no backfill needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->text('date_of_birth')->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('sex');
        });
    }
};
