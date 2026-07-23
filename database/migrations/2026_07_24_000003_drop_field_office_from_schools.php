<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schools now belong to a testing center, which is shared across field
        // offices — so a school no longer has a single owning office. Access is
        // resolved through the testing center's field_office_testing_center links.
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_office_id');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable()->after('id')->constrained('field_offices')->cascadeOnDelete();
        });
    }
};
