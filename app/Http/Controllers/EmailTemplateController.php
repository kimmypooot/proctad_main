<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
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
