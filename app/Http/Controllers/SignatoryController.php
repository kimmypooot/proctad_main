<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FieldOffice;
use App\Models\Signatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SignatoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Signatory::class);

        $user = $request->user();

        return Inertia::render('Signatories/Index', [
            'signatories' => Signatory::with('fieldOffice:id,name,code')
                ->orderByRaw('field_office_id is not null')
                ->orderBy('field_office_id')
                ->orderByDesc('active')
                ->get()
                ->map(fn (Signatory $signatory) => [
                    ...$signatory->only('id', 'name', 'position', 'active', 'field_office_id'),
                    'field_office' => $signatory->fieldOffice?->only('id', 'name', 'code'),
                    'can_manage' => $user->can('update', $signatory),
                ]),
            'fieldOffices' => $this->assignableScopes($user),
            'can' => ['create' => $user->can('create', Signatory::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Signatory::class);

        Signatory::create($this->validated($request));

        return back()->with('success', 'Signatory added.');
    }

    public function update(Request $request, Signatory $signatory): RedirectResponse
    {
        Gate::authorize('update', $signatory);

        $signatory->update($this->validated($request));

        return back()->with('success', 'Signatory updated. Previously issued IDs and certificates are not affected.');
    }

    public function destroy(Signatory $signatory): RedirectResponse
    {
        Gate::authorize('delete', $signatory);

        $signatory->delete();

        return back()->with('success', 'Signatory removed.');
    }

    private function validated(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'field_office_id' => [
                'nullable',
                'exists:field_offices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($user->role === UserRole::FoAdmin && (int) $value !== $user->field_office_id) {
                        $fail('You can only manage signatories of your own Testing Center.');
                    }
                },
            ],
        ]);
    }

    /**
     * Scopes the current user may assign: FO admins only their own office;
     * region-wide roles get every office plus the region-wide option (null).
     */
    private function assignableScopes($user): array
    {
        if ($user->role === UserRole::FoAdmin) {
            return FieldOffice::whereKey($user->field_office_id)->get(['id', 'name', 'code'])->all();
        }

        return FieldOffice::orderBy('name')->get(['id', 'name', 'code'])->all();
    }
}
