<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\TestingCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', School::class);

        School::create($this->validated($request));

        return back()->with('success', 'School added.');
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        Gate::authorize('update', $school);

        $school->update($this->validated($request, $school));

        return back()->with('success', 'School updated.');
    }

    public function destroy(School $school): RedirectResponse
    {
        Gate::authorize('delete', $school);

        $school->delete();

        return back()->with('success', 'School removed.');
    }

    private function validated(Request $request, ?School $school = null): array
    {
        $user = $request->user();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'testing_center_id' => [
                $school ? 'sometimes' : 'required',
                'exists:testing_centers,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if (! $user->role->isFieldOfficeScoped()) {
                        return;
                    }

                    $handled = TestingCenter::whereKey($value)
                        ->whereHas('fieldOffices', fn ($q) => $q->whereKey($user->field_office_id))
                        ->exists();

                    if (! $handled) {
                        $fail('You can only manage schools within a testing center your field office handles.');
                    }
                },
            ],
        ]);
    }
}
