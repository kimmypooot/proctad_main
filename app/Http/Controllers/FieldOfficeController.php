<?php

namespace App\Http\Controllers;

use App\Models\FieldOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FieldOfficeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FieldOffice::class);

        $fieldOffices = FieldOffice::withCount('users')->orderBy('name')->get();

        return Inertia::render('Settings/FieldOffices/Index', [
            'fieldOffices' => $fieldOffices->map(fn (FieldOffice $office) => [
                ...$office->only('id', 'name', 'code', 'address', 'is_active'),
                'users_count' => $office->users_count,
            ]),
            'stats' => [
                'total' => $fieldOffices->count(),
                'active' => $fieldOffices->where('is_active', true)->count(),
                'hidden' => $fieldOffices->where('is_active', false)->count(),
            ],
            'can' => ['manage' => $request->user()->can('manage', FieldOffice::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', FieldOffice::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:field_offices,code'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        FieldOffice::create($validated);

        return back()->with('success', 'Field office added.');
    }

    public function update(Request $request, FieldOffice $fieldOffice): RedirectResponse
    {
        Gate::authorize('manage', FieldOffice::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:field_offices,code,'.$fieldOffice->id],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $fieldOffice->update($validated);

        return back()->with('success', 'Field office updated.');
    }
}
