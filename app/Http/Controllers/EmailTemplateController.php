<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        return Inertia::render('Settings/EmailTemplates/Index', [
            'templates' => EmailTemplate::orderBy('name')->get()
                ->map(fn (EmailTemplate $template) => [
                    ...$template->only('id', 'code', 'name', 'subject', 'body_html', 'body_plain', 'is_active'),
                    'variables' => $template->variables ?? [],
                ]),
            'can' => ['manage' => $request->user()->can('manage', EmailTemplate::class)],
            'logs' => $this->logs($request),
            'logFilters' => $request->only('log_search', 'log_status', 'log_template'),
            'logStatuses' => ['sent', 'failed', 'skipped'],
        ]);
    }

    /**
     * Delivery history for the templates above — what was actually sent, to
     * whom, and whether it arrived.
     *
     * Bodies are omitted here and fetched per row by EmailLogController; see
     * the note there. `page` is named log_page so paginating this list cannot
     * collide with any other paginator on the page.
     */
    private function logs(Request $request): LengthAwarePaginator
    {
        return EmailLog::query()
            ->with('sentBy:id,name', 'template:id,name')
            ->when($request->filled('log_search'), function ($q) use ($request) {
                $term = $request->string('log_search');
                $q->where(fn ($qq) => $qq->where('recipient_email', 'like', "%{$term}%")
                    ->orWhere('recipient_name', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%"));
            })
            ->when($request->filled('log_status'), fn ($q) => $q->where('status', $request->string('log_status')))
            ->when($request->filled('log_template'), fn ($q) => $q->where('email_template_id', $request->integer('log_template')))
            ->latest('id')
            ->paginate(15, pageName: 'log_page')
            ->withQueryString()
            ->through(fn (EmailLog $log) => [
                'id' => $log->id,
                'recipient_email' => $log->recipient_email,
                'recipient_name' => $log->recipient_name,
                'subject' => $log->subject,
                'template_name' => $log->template?->name,
                'email_type' => $log->email_type,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'sent_by' => $log->sentBy?->name,
                // Failed and skipped rows never get a sent_at, so the list
                // would sort and read as undated without the fallback.
                'at' => ($log->sent_at ?? $log->created_at)?->format('M d, Y g:i A'),
                // Pre-dates the body columns, so there is nothing to open —
                // the row is still real history and stays listed.
                'has_body' => $log->body_html !== null,
            ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        Gate::authorize('manage', EmailTemplate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_plain' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $emailTemplate->update($validated);

        return back()->with('success', "\"{$emailTemplate->name}\" template updated.");
    }
}
