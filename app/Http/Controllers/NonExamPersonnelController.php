<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelType;
use App\Enums\UserRole;
use App\Http\Requests\StoreNonExamPersonnelRequest;
use App\Http\Requests\UpdateNonExamPersonnelRequest;
use App\Models\FieldOffice;
use App\Models\NonExamPersonnel;
use App\Models\User;
use App\Services\IdCardPdfService;
use App\Support\NepIdCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NonExamPersonnelController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NonExamPersonnel::class);

        $user = $request->user();
        $regionWide = $user->role->isRegionWide();

        $personnel = NonExamPersonnel::query()
            ->with('fieldOffice:id,name,code')
            ->when(! $regionWide, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('nep_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('agency', 'like', "%{$term}%"));
            })
            ->when($request->filled('personnel_type'), fn ($q) => $q->where('personnel_type', $request->string('personnel_type')))
            ->when($regionWide && $request->filled('field_office_id'),
                fn ($q) => $q->where('field_office_id', $request->integer('field_office_id')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (NonExamPersonnel $nep) => $this->presentForList($nep));

        return Inertia::render('NonExamPersonnel/Index', [
            'personnel' => $personnel,
            'filters' => $request->only('search', 'personnel_type', 'field_office_id'),
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
            'personnelTypes' => $this->personnelTypeOptions(),
            'can' => ['create' => $user->can('create', NonExamPersonnel::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', NonExamPersonnel::class);

        return Inertia::render('NonExamPersonnel/Create', [
            'fieldOffices' => $this->assignableFieldOffices($request->user()),
            'personnelTypes' => $this->personnelTypeOptions(),
        ]);
    }

    public function store(StoreNonExamPersonnelRequest $request): RedirectResponse
    {
        Gate::authorize('create', NonExamPersonnel::class);

        $validated = $this->withPhoto($request, $request->validated());

        $nep = NonExamPersonnel::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('non-exam-personnel.show', $nep)
            ->with('success', "Non-exam personnel registered with ID {$nep->nep_id}.");
    }

    public function show(NonExamPersonnel $nonExamPersonnel): Response
    {
        Gate::authorize('view', $nonExamPersonnel);

        return Inertia::render('NonExamPersonnel/Show', [
            'nep' => $this->presentForList($nonExamPersonnel) + [
                'middle_name' => $nonExamPersonnel->middle_name,
                'suffix' => $nonExamPersonnel->suffix,
                'sex' => $nonExamPersonnel->sex,
                'contact_number' => $nonExamPersonnel->contact_number,
                'email' => $nonExamPersonnel->email,
                'position' => $nonExamPersonnel->position,
                'created_at' => $nonExamPersonnel->created_at->toDateString(),
            ],
            'idCard' => NepIdCard::data($nonExamPersonnel),
            'can' => ['update' => request()->user()->can('update', $nonExamPersonnel)],
        ]);
    }

    public function edit(Request $request, NonExamPersonnel $nonExamPersonnel): Response
    {
        Gate::authorize('update', $nonExamPersonnel);

        return Inertia::render('NonExamPersonnel/Edit', [
            'nep' => $nonExamPersonnel->only([
                'id', 'nep_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex',
                'contact_number', 'email', 'agency', 'position', 'personnel_type',
                'field_office_id', 'is_active',
            ]),
            'fieldOffices' => $this->assignableFieldOffices($request->user()),
            'personnelTypes' => $this->personnelTypeOptions(),
        ]);
    }

    public function update(UpdateNonExamPersonnelRequest $request, NonExamPersonnel $nonExamPersonnel): RedirectResponse
    {
        Gate::authorize('update', $nonExamPersonnel);

        $validated = $this->withPhoto($request, $request->validated(), $nonExamPersonnel);

        $nonExamPersonnel->update($validated);

        return redirect()
            ->route('non-exam-personnel.show', $nonExamPersonnel)
            ->with('success', 'Record updated.');
    }

    public function destroy(NonExamPersonnel $nonExamPersonnel): RedirectResponse
    {
        Gate::authorize('delete', $nonExamPersonnel);

        $nonExamPersonnel->delete();

        return redirect()
            ->route('non-exam-personnel.index')
            ->with('success', "{$nonExamPersonnel->nep_id} removed.");
    }

    private function withPhoto(Request $request, array $validated, ?NonExamPersonnel $nep = null): array
    {
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            if ($nep?->photo_path) {
                Storage::disk('local')->delete($nep->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('nep-photos', 'local');
        }

        return $validated;
    }

    public function photo(NonExamPersonnel $nonExamPersonnel)
    {
        Gate::authorize('view', $nonExamPersonnel);

        abort_unless(
            $nonExamPersonnel->photo_path && Storage::disk('local')->exists($nonExamPersonnel->photo_path),
            404,
        );

        return Storage::disk('local')->response($nonExamPersonnel->photo_path);
    }

    public function downloadIdCard(NonExamPersonnel $nonExamPersonnel, IdCardPdfService $service): HttpResponse
    {
        Gate::authorize('view', $nonExamPersonnel);

        $pdf = $service->renderNep($nonExamPersonnel);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"nep-id-{$nonExamPersonnel->nep_id}.pdf\"",
        ]);
    }

    private function assignableFieldOffices(User $user)
    {
        return FieldOffice::query()
            ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->whereKey($user->field_office_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function personnelTypeOptions(): array
    {
        return collect(PersonnelType::cases())
            ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])
            ->all();
    }

    private function presentForList(NonExamPersonnel $nep): array
    {
        return [
            'id' => $nep->id,
            'nep_id' => $nep->nep_id,
            'name' => $nep->name,
            'first_name' => $nep->first_name,
            'last_name' => $nep->last_name,
            'agency' => $nep->agency,
            'personnel_type' => $nep->personnel_type->value,
            'personnel_type_label' => $nep->personnel_type->label(),
            'field_office' => $nep->fieldOffice?->only('id', 'name', 'code'),
            'is_active' => $nep->is_active,
        ];
    }
}
