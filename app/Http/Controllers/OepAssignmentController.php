<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\OepAssignment;
use App\Models\OepAttendance;
use App\Models\OtherExaminationPersonnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OepAssignmentController extends Controller
{
    /**
     * Attach an other examination personnel to an examination venue.
     */
    public function store(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', OepAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'other_examination_personnel_id' => [
                'required',
                Rule::exists('other_examination_personnel', 'id'),
                Rule::unique('oep_assignments', 'other_examination_personnel_id')
                    ->where('examination_school_id', $venue->id),
            ],
        ], [
            'other_examination_personnel_id.unique' => 'This person is already assigned to this venue.',
        ]);

        $oep = OtherExaminationPersonnel::findOrFail($validated['other_examination_personnel_id']);

        abort_if(
            $user->role->isFieldOfficeScoped()
                && ! $oep->isWithinJurisdictionOf($user),
            403,
        );

        $venue->oepAssignments()->create([
            'other_examination_personnel_id' => $oep->id,
            'assigned_by' => $user->id,
        ]);

        return back()->with('success', "{$oep->name} added to the venue.");
    }

    public function destroy(OepAssignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Removed from venue.');
    }

    /**
     * Toggle an other examination personnel's attendance at the venue they're assigned to.
     */
    public function markAttendance(Request $request, OepAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate(['present' => ['required', 'boolean']]);

        $attendance = OepAttendance::where('other_examination_personnel_id', $assignment->other_examination_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)
            ->first();

        if ($validated['present']) {
            if ($attendance === null) {
                OepAttendance::create([
                    'other_examination_personnel_id' => $assignment->other_examination_personnel_id,
                    'examination_school_id' => $assignment->examination_school_id,
                    'status' => 'present',
                    'scan_method' => 'manual',
                    'scanned_at' => now(),
                    'scanned_by' => $request->user()->id,
                ]);
            }
        } elseif ($attendance !== null) {
            $attendance->delete();
        }

        return back()->with('success', 'Attendance updated.');
    }
}
