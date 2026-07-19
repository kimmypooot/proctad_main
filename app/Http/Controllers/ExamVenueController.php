<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExamVenueController extends Controller
{
    /**
     * Attach a school as a venue for this examination.
     */
    public function store(Request $request, Examination $examination): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);

        $user = $request->user();

        $validated = $request->validate([
            'school_id' => [
                'required',
                Rule::exists('schools', 'id'),
                Rule::unique('examination_school', 'school_id')->where('examination_id', $examination->id),
            ],
        ], [
            'school_id.unique' => 'This school is already a venue for this examination.',
        ]);

        $school = School::findOrFail($validated['school_id']);

        abort_if(
            $user->role->isFieldOfficeScoped() && $school->field_office_id !== $user->field_office_id,
            403,
        );

        $examination->venues()->create([
            'school_id' => $school->id,
            'assigned_by' => $user->id,
        ]);

        return back()->with('success', "{$school->name} added as a venue.");
    }

    /**
     * Detach a venue (and its rooms, via cascade) from the examination.
     */
    public function destroy(ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('delete', $venue);

        $venue->delete();

        return back()->with('success', 'Venue removed.');
    }
}
