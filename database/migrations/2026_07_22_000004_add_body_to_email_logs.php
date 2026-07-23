<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the rendered body of each email alongside the existing subject and
 * status, so an admin can see what a recipient was actually sent.
 *
 * The body is captured at send time rather than re-rendered from the template
 * on demand. Templates are admin-editable (EmailTemplateController::update),
 * so re-rendering would show today's wording over yesterday's delivery — which
 * is precisely wrong for the question this answers: "what did we tell them?"
 * The same reasoning applies to the placeholder data, which is substituted in
 * and then gone; nothing else in the system can reconstruct it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->longText('body_html')->nullable()->after('subject');
            $table->longText('body_plain')->nullable()->after('body_html');
            // Which template produced it, for grouping the log by template.
            // nullOnDelete rather than cascade: the delivery record must
            // outlive a template that is later removed.
            $table->foreignId('email_template_id')->nullable()->after('email_type')
                ->constrained('email_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_template_id');
            $table->dropColumn(['body_html', 'body_plain']);
        });
    }
};
