<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('member')->index()->after('suffix');
            $table->foreignId('field_office_id')
                ->nullable()
                ->after('role')
                ->constrained('field_offices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_office_id');
            $table->dropColumn('role');
        });
    }
};
