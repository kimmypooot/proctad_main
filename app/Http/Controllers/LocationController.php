<?php

namespace App\Http\Controllers;

use App\Models\FieldOffice;
use App\Models\School;
use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified drill-down for the Field Office → Testing Center → School hierarchy.
 * Testing centers are shared across field offices (many-to-many), so a center
 * can appear under more than one office. Create/edit/delete still post to the
 * per-entity controllers (field-offices, testing-centers, schools).
 */
class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        // Reuse the School gate: everyone but Members may browse the registry.
        Gate::authorize('viewAny', School::class);

        $user = $request->user();
        $scoped = $user->role->isFieldOfficeScoped();

        $fieldOffices = FieldOffice::query()
            ->when($scoped, fn ($q) => $q->whereIn('id', $user->scopedFieldOfficeIds()))
            ->withCount(['testingCenters', 'members'])
            ->orderBy('name')
            ->get()
            ->map(fn (FieldOffice $office) => [
                ...$office->only('id', 'name', 'code', 'address', 'is_active'),
                'testing_centers_count' => $office->testing_centers_count,
                // Staff operating at the centers this office handles. Counted at
                // the shared-center level, so offices sharing a center (Leyte I
                // and Leyte II at Tacloban City) report the same pool.
                'users_count' => User::whereHas(
                    'testingCenters.fieldOffices',
                    fn ($q) => $q->whereKey($office->id),
                )->count(),
                'members_count' => $office->members_count,
            ]);

        $testingCenters = TestingCenter::query()
            ->when($scoped, fn ($q) => $q->forFieldOffice($user->field_office_id))
            ->with('fieldOffices:id,name')
            ->withCount(['schools', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (TestingCenter $center) => [
                ...$center->only('id', 'name', 'is_active'),
                'field_office_ids' => $center->fieldOffices->pluck('id'),
                // The office administering this center, whose signatory signs
                // for the members serving there. Only contested where a center
                // is shared (Tacloban City), but shown everywhere so it is
                // never a mystery whose signature appears.
                'administering_field_office_id' => $center->fieldOffices
                    ->firstWhere(fn ($office) => (bool) $office->pivot->is_primary)?->id,
                'handling_offices' => $center->fieldOffices
                    ->map(fn ($office) => $office->only('id', 'name'))
                    ->values(),
                'schools_count' => $center->schools_count,
                'users_count' => $center->users_count,
                'can_manage' => $user->can('update', $center),
                'can_designate_administering' => $user->can('designateAdministering', $center),
            ]);

        $schools = School::query()
            ->when($scoped, fn ($q) => $q->forFieldOffice($user->field_office_id))
            ->withCount('examinationSchools')
            ->orderBy('name')
            ->get()
            ->map(fn (School $school) => [
                ...$school->only(
                    'id', 'name', 'testing_center_id',
                    'contact_person', 'contact_number', 'contact_email', 'is_active',
                ),
                'venues_count' => $school->examination_schools_count,
                'can_manage' => $user->can('update', $school),
            ]);

        return Inertia::render('Locations/Index', [
            'fieldOffices' => $fieldOffices,
            'testingCenters' => $testingCenters,
            // Every center region-wide, so an office can link an existing one
            // (e.g. Leyte II joining Tacloban City) instead of duplicating it.
            'allTestingCenters' => TestingCenter::orderBy('name')->get(['id', 'name'])
                ->map(fn (TestingCenter $c) => $c->only('id', 'name')),
            'schools' => $schools,
            'scope' => [
                'field_office_scoped' => $scoped,
                'field_office_id' => $scoped ? $user->field_office_id : null,
            ],
            'can' => [
                'manageFieldOffices' => $user->can('manage', FieldOffice::class),
                'createTestingCenter' => $user->can('create', TestingCenter::class),
                'createSchool' => $user->can('create', School::class),
            ],
        ]);
    }
}
