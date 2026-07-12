<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->foreignId('examination_school_id')->nullable()->after('examination_id')
                ->constrained('examination_school')->nullOnDelete();
            $table->foreignId('exam_room_id')->nullable()->after('examination_school_id')
                ->constrained('exam_rooms')->nullOnDelete();

            // Assignment confirmation workflow (member accepts/declines via signed URL email).
            $table->string('status', 20)->default('pending')->after('role')->index();
            $table->timestamp('confirmation_sent_at')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('confirmation_sent_at');
            $table->string('decline_reason')->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('examination_school_id');
            $table->dropConstrainedForeignId('exam_room_id');
            $table->dropColumn(['status', 'confirmation_sent_at', 'responded_at', 'decline_reason']);
        });
    }
};
