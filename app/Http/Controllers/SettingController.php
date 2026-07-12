<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Setting::class);

        return Inertia::render('Settings/General/Index', [
            'settings' => Setting::orderBy('key')->get()
                ->map(fn (Setting $setting) => $setting->only('id', 'key', 'value', 'type', 'description', 'is_public')),
            'can' => ['manage' => $request->user()->can('manage', Setting::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.]+$/', 'unique:settings,key'],
            'value' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['string', 'number', 'boolean', 'json'])],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['required', 'boolean'],
        ]);

        Setting::create([
            'key' => $validated['key'],
            'value' => $validated['value'] ?? '',
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Setting added.');
    }

    public function update(Request $request, Setting $setting): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $validated = $request->validate([
            'value' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['required', 'boolean'],
        ]);

        $setting->update([
            'value' => $validated['value'] ?? '',
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "\"{$setting->key}\" updated.");
    }

    public function destroy(Setting $setting): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $setting->delete();

        return back()->with('success', "\"{$setting->key}\" removed.");
    }
}
