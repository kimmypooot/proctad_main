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

        return Inertia::render('Settings/FieldOffices/Index', [
            'fieldOffices' => FieldOffice::withCount('users')->orderBy('name')->get()
                ->map(fn (FieldOffice $office) => [
                    ...$office->only('id', 'name', 'code', 'address'),
                    'users_count' => $office->users_count,
                ]),
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
        ]);

        $fieldOffice->update($validated);

        return back()->with('success', 'Field office updated.');
    }
}
