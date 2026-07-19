<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatories', function (Blueprint $table) {
            // Private-disk path to the signatory's e-signature image. Nullable:
            // certificates remain valid signed by hand over the printed name,
            // which is what every existing signatory does today.
            $table->string('signature_path')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('signatories', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
