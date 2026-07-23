<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The body of one sent email, fetched on demand.
 *
 * Kept off the paginated list deliberately: a rendered HTML body runs to
 * several kilobytes, and shipping fifteen of them with every page load would
 * cost far more than the one an admin actually opens.
 */
class EmailLogController extends Controller
{
    public function show(EmailLog $emailLog): JsonResponse
    {
        // Same audience as the templates themselves — these bodies contain
        // recipients' names and signed links, so they are not for general
        // staff eyes.
        Gate::authorize('viewAny', EmailTemplate::class);

        return response()->json([
            'id' => $emailLog->id,
            'subject' => $emailLog->subject,
            'body_html' => $emailLog->body_html,
            'body_plain' => $emailLog->body_plain,
            'recipient_email' => $emailLog->recipient_email,
            'recipient_name' => $emailLog->recipient_name,
            'status' => $emailLog->status,
            'error_message' => $emailLog->error_message,
            'sent_at' => $emailLog->sent_at?->format('M d, Y g:i A'),
            'created_at' => $emailLog->created_at?->format('M d, Y g:i A'),
        ]);
    }
}
