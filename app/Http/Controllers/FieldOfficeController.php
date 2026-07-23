<?php

namespace App\Http\Controllers;

use App\Models\FieldOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FieldOfficeController extends Controller
{
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

    public function destroy(FieldOffice $fieldOffice): RedirectResponse
    {
        Gate::authorize('manage', FieldOffice::class);

        // A field office anchors users and members. Refuse to delete while any
        // remain so records are never silently orphaned. Its testing-center
        // links are shared, so they simply detach (the centers live on).
        if ($fieldOffice->users()->exists() || $fieldOffice->members()->exists()) {
            return back()->with('error', 'Reassign or remove this field office\'s users and members before deleting it.');
        }

        $fieldOffice->delete();

        return back()->with('success', 'Field office removed.');
    }
}
