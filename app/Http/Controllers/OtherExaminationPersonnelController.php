<?php

namespace App\Http\Controllers;

use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use App\Enums\UserRole;
use App\Http\Requests\StoreOtherExaminationPersonnelRequest;
use App\Http\Requests\UpdateOtherExaminationPersonnelRequest;
use App\Models\FieldOffice;
use App\Models\OtherExaminationPersonnel;
use App\Models\TestingCenter;
use App\Models\User;
use App\Services\IdCardPdfService;
use App\Support\DesignationRegistry;
use App\Support\OepIdCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OtherExaminationPersonnelController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', OtherExaminationPersonnel::class);

        $user = $request->user();
        $regionWide = $user->role->isRegionWide();

        $personnel = OtherExaminationPersonnel::query()
            ->with(['fieldOffice:id,name,code', 'testingCenter:id,name'])
            ->when(! $regionWide, fn ($q) => $q->withinJurisdictionOf($user))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('oep_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('agency', 'like', "%{$term}%"));
            })
            ->when($request->filled('personnel_type'), fn ($q) => $q->where('personnel_type', $request->string('personnel_type')))
            ->when($regionWide && $request->filled('field_office_id'),
                fn ($q) => $q->where('field_office_id', $request->integer('field_office_id')))
            // Centers are shared between offices, so FO-scoped staff get this
            // filter too — it narrows within their jurisdiction rather than
            // widening it, unlike the office filter above.
            ->when($request->filled('testing_center_id'),
                fn ($q) => $q->where('testing_center_id', $request->integer('testing_center_id')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (OtherExaminationPersonnel $oep) => $this->presentForList($oep));

        return Inertia::render('OtherExaminationPersonnel/Index', [
            'personnel' => $personnel,
            'filters' => $request->only('search', 'personnel_type', 'field_office_id', 'testing_center_id'),
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
            'testingCenters' => $this->assignableTestingCenters($user)
                ->map(fn (array $center) => ['id' => $center['id'], 'name' => $center['name']]),
            'personnelTypes' => $this->personnelTypeOptions(),
            'can' => ['create' => $user->can('create', OtherExaminationPersonnel::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', OtherExaminationPersonnel::class);

        return Inertia::render('OtherExaminationPersonnel/Create', [
            'fieldOffices' => $this->assignableFieldOffices($request->user()),
            'testingCenters' => $this->assignableTestingCenters($request->user()),
            'personnelTypes' => $this->personnelTypeOptions(),
        ]);
    }

    public function store(StoreOtherExaminationPersonnelRequest $request): RedirectResponse
    {
        Gate::authorize('create', OtherExaminationPersonnel::class);

        $validated = $this->withPhoto($request, $request->validated());

        $oep = OtherExaminationPersonnel::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('other-examination-personnel.index')
            ->with('success', "Other examination personnel registered with ID {$oep->oep_id}.");
    }

    public function show(OtherExaminationPersonnel $otherExaminationPersonnel): RedirectResponse
    {
        Gate::authorize('view', $otherExaminationPersonnel);

        return redirect()->route('other-examination-personnel.index');
    }

    public function details(OtherExaminationPersonnel $otherExaminationPersonnel): JsonResponse
    {
        Gate::authorize('view', $otherExaminationPersonnel);

        return response()->json([
            'oep' => $this->presentForList($otherExaminationPersonnel) + [
                'middle_name' => $otherExaminationPersonnel->middle_name,
                'suffix' => $otherExaminationPersonnel->suffix,
                'sex' => $otherExaminationPersonnel->sex,
                'contact_number' => $otherExaminationPersonnel->contact_number,
                'email' => $otherExaminationPersonnel->email,
                'position' => $otherExaminationPersonnel->position,
                'created_at' => $otherExaminationPersonnel->created_at->toDateString(),
            ],
            'idCard' => OepIdCard::data($otherExaminationPersonnel),
            'can' => ['update' => request()->user()->can('update', $otherExaminationPersonnel)],
        ]);
    }

    public function edit(Request $request, OtherExaminationPersonnel $otherExaminationPersonnel): RedirectResponse
    {
        Gate::authorize('update', $otherExaminationPersonnel);

        return redirect()->route('other-examination-personnel.index');
    }

    public function editData(Request $request, OtherExaminationPersonnel $otherExaminationPersonnel): JsonResponse
    {
        Gate::authorize('update', $otherExaminationPersonnel);

        return response()->json([
            'oep' => $otherExaminationPersonnel->only([
                'id', 'oep_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex',
                'contact_number', 'email', 'agency', 'position', 'personnel_type',
                'field_office_id', 'testing_center_id', 'is_active',
            ]),
            'fieldOffices' => $this->assignableFieldOffices($request->user(), $otherExaminationPersonnel->field_office_id),
            'testingCenters' => $this->assignableTestingCenters($request->user(), $otherExaminationPersonnel->testing_center_id),
            'personnelTypes' => $this->personnelTypeOptions(),
        ]);
    }

    public function update(UpdateOtherExaminationPersonnelRequest $request, OtherExaminationPersonnel $otherExaminationPersonnel): RedirectResponse
    {
        Gate::authorize('update', $otherExaminationPersonnel);

        $validated = $this->withPhoto($request, $request->validated(), $otherExaminationPersonnel);

        $otherExaminationPersonnel->update($validated);

        return redirect()
            ->route('other-examination-personnel.index')
            ->with('success', 'Record updated.');
    }

    public function destroy(OtherExaminationPersonnel $otherExaminationPersonnel): RedirectResponse
    {
        Gate::authorize('delete', $otherExaminationPersonnel);

        $otherExaminationPersonnel->delete();

        return redirect()
            ->route('other-examination-personnel.index')
            ->with('success', "{$otherExaminationPersonnel->oep_id} removed.");
    }

    private function withPhoto(Request $request, array $validated, ?OtherExaminationPersonnel $oep = null): array
    {
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            if ($oep?->photo_path) {
                Storage::disk('local')->delete($oep->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('oep-photos', 'local');
        }

        return $validated;
    }

    public function photo(OtherExaminationPersonnel $otherExaminationPersonnel)
    {
        Gate::authorize('view', $otherExaminationPersonnel);

        abort_unless(
            $otherExaminationPersonnel->photo_path && Storage::disk('local')->exists($otherExaminationPersonnel->photo_path),
            404,
        );

        // Same reasoning as MemberController::photo — an inline, same-origin
        // response for a file somebody else uploaded.
        return Storage::disk('local')->response(
            $otherExaminationPersonnel->photo_path,
            headers: MemberController::FILE_HEADERS,
        );
    }

    public function downloadIdCard(OtherExaminationPersonnel $otherExaminationPersonnel, IdCardPdfService $service): HttpResponse
    {
        Gate::authorize('view', $otherExaminationPersonnel);

        $pdf = $service->renderOep($otherExaminationPersonnel);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"oep-id-{$otherExaminationPersonnel->oep_id}.pdf\"",
        ]);
    }

    private function assignableFieldOffices(User $user, ?int $currentId = null)
    {
        return FieldOffice::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q2) => $q2->orWhere('id', $currentId)))
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->whereIn('id', $user->scopedFieldOfficeIds()))
            ->orderBy('name')
            // is_regional so the form knows which choice means region-wide, and
            // can drop the testing center accordingly.
            ->get(['id', 'name', 'code', 'is_regional']);
    }

    /**
     * Testing centers this user may place someone in, tagged with the offices
     * handling each so the form can narrow the list once an office is chosen —
     * the same shape MemberController::assignableTestingCenters returns, since
     * both forms make the same choice. The current center is always included,
     * even if since deactivated, so editing never silently moves someone.
     */
    private function assignableTestingCenters(User $user, ?int $currentId = null)
    {
        return TestingCenter::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q2) => $q2->orWhere('id', $currentId)))
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->whereIn('id', $user->scopedTestingCenterIds()))
            ->with('fieldOffices:id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TestingCenter $center) => [
                'id' => $center->id,
                'name' => $center->name,
                'field_office_ids' => $center->fieldOffices->pluck('id'),
            ]);
    }

    private function personnelTypeOptions(): array
    {
        // Only those still in use, so a deactivated type stops being offered on
        // new records without disturbing the personnel already filed under it.
        return collect(DesignationRegistry::forSection(PayeeType::PersonnelType))
            ->map(fn (array $row) => ['value' => $row['key'], 'label' => $row['label']])
            ->all();
    }

    private function presentForList(OtherExaminationPersonnel $oep): array
    {
        return [
            'id' => $oep->id,
            'oep_id' => $oep->oep_id,
            'name' => $oep->name,
            'first_name' => $oep->first_name,
            'last_name' => $oep->last_name,
            'agency' => $oep->agency,
            'personnel_type' => $oep->personnel_type->value,
            'personnel_type_label' => $oep->personnel_type->label(),
            'field_office' => $oep->fieldOffice?->only('id', 'name', 'code'),
            'testing_center' => $oep->testingCenter?->only('id', 'name'),
            // Distinguishes "serves everywhere" from "not placed yet": both have
            // no center, and only the first is a normal state.
            'is_region_wide' => $oep->isRegionWide(),
            'is_active' => $oep->is_active,
        ];
    }
}
