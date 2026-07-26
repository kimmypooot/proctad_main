<?php

namespace App\Http\Controllers;

use App\Models\FieldOffice;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TestingCenterController extends Controller
{
    /**
     * Add a testing center to a field office. Either links an existing center
     * (shared hosting, e.g. Leyte II joining Tacloban City) or creates a new
     * one, then attaches it to the office via the many-to-many pivot.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TestingCenter::class);

        $user = $request->user();

        $validated = $request->validate([
            'field_office_id' => [
                'required', 'exists:field_offices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($user->role->isFieldOfficeScoped() && (int) $value !== $user->field_office_id) {
                        $fail('You can only manage testing centers of your own field office.');
                    }
                },
            ],
            // Link an existing center...
            'testing_center_id' => ['nullable', 'exists:testing_centers,id'],
            // ...or create a new one.
            'name' => ['required_without:testing_center_id', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $center = ! empty($validated['testing_center_id'])
            ? TestingCenter::findOrFail($validated['testing_center_id'])
            : TestingCenter::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

        $center->fieldOffices()->syncWithoutDetaching([$validated['field_office_id']]);

        // The office's staff now operate at this center, so add them to its
        // staff pool. The UserObserver only reacts to user saves, so a center
        // gaining an office has to link that office's existing users here —
        // otherwise the shared staff count would miss them.
        $center->users()->syncWithoutDetaching(
            User::where('field_office_id', $validated['field_office_id'])->pluck('id'),
        );

        return back()->with('success', 'Testing center added.');
    }

    public function update(Request $request, TestingCenter $testingCenter): RedirectResponse
    {
        Gate::authorize('update', $testingCenter);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $testingCenter->update($validated);

        return back()->with('success', 'Testing center updated.');
    }

    /**
     * Hand administration of a center to one of its handling offices. That
     * office's signatory signs the ID cards and certificates of the members who
     * serve there, so it moves when administration does. Only contested where a
     * center is shared, but accepted for any center so the flag is never unset.
     */
    public function designateAdministering(Request $request, TestingCenter $testingCenter): RedirectResponse
    {
        Gate::authorize('designateAdministering', $testingCenter);

        $validated = $request->validate([
            'field_office_id' => [
                'required', 'integer',
                // Must already handle this center — administration cannot be
                // handed to an office that does not serve it.
                Rule::exists('field_office_testing_center', 'field_office_id')
                    ->where('testing_center_id', $testingCenter->id),
            ],
        ], [
            'field_office_id.exists' => 'That field office does not handle this testing center.',
        ]);

        $testingCenter->designateAdministeringFieldOffice((int) $validated['field_office_id']);

        $office = FieldOffice::find($validated['field_office_id']);

        return back()->with(
            'success',
            "{$office->name} now administers {$testingCenter->name}.",
        );
    }

    public function destroy(TestingCenter $testingCenter): RedirectResponse
    {
        Gate::authorize('delete', $testingCenter);

        // Deleting is global (it affects every field office sharing the center),
        // so it is blocked while any school still sits under it.
        if ($testingCenter->schools()->exists()) {
            return back()->with('error', 'Reassign or remove this testing center\'s schools before deleting it.');
        }

        $testingCenter->delete();

        return back()->with('success', 'Testing center removed.');
    }
}
