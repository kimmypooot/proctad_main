<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', School::class);

        $user = $request->user();

        $schools = School::with('fieldOffice:id,name,code')
            ->withCount('examinationSchools')
            ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->orderBy('name')
            ->get();

        return Inertia::render('Schools/Index', [
            'schools' => $schools->map(fn (School $school) => [
                ...$school->only('id', 'name', 'municipality', 'contact_person', 'contact_number', 'contact_email', 'is_active'),
                'field_office' => $school->fieldOffice?->only('id', 'name', 'code'),
                'venues_count' => $school->examination_schools_count,
                'can_manage' => $user->can('update', $school),
            ]),
            'stats' => [
                'total' => $schools->count(),
                'active' => $schools->where('is_active', true)->count(),
                'inactive' => $schools->where('is_active', false)->count(),
            ],
            'fieldOffices' => $this->assignableScopes($user),
            'can' => ['create' => $user->can('create', School::class)],
        ]);
    }

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
            'municipality' => ['required', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'field_office_id' => [
                $school ? 'sometimes' : 'required',
                'exists:field_offices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($user->role === UserRole::FoAdmin && (int) $value !== $user->field_office_id) {
                        $fail('You can only manage schools of your own Testing Center.');
                    }
                },
            ],
        ]);
    }

    private function assignableScopes(User $user): array
    {
        if ($user->role === UserRole::FoAdmin) {
            return FieldOffice::whereKey($user->field_office_id)->get(['id', 'name', 'code'])->all();
        }

        return FieldOffice::orderBy('name')->get(['id', 'name', 'code'])->all();
    }
}
