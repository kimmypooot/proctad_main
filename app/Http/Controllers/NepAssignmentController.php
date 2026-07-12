<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\NepAssignment;
use App\Models\NepAttendance;
use App\Models\NonExamPersonnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NepAssignmentController extends Controller
{
    /**
     * Attach a non-exam personnel to an examination venue.
     */
    public function store(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', NepAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'non_exam_personnel_id' => [
                'required',
                Rule::exists('non_exam_personnel', 'id'),
                Rule::unique('nep_assignments', 'non_exam_personnel_id')
                    ->where('examination_school_id', $venue->id),
            ],
        ], [
            'non_exam_personnel_id.unique' => 'This person is already assigned to this venue.',
        ]);

        $nep = NonExamPersonnel::findOrFail($validated['non_exam_personnel_id']);

        abort_if(
            $user->role === UserRole::FoAdmin && $nep->field_office_id !== $user->field_office_id,
            403,
        );

        $venue->nepAssignments()->create([
            'non_exam_personnel_id' => $nep->id,
            'assigned_by' => $user->id,
        ]);

        return back()->with('success', "{$nep->name} added to the venue.");
    }

    public function destroy(NepAssignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Removed from venue.');
    }

    /**
     * Toggle a non-exam personnel's attendance at the venue they're assigned to.
     */
    public function markAttendance(Request $request, NepAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate(['present' => ['required', 'boolean']]);

        $attendance = NepAttendance::where('non_exam_personnel_id', $assignment->non_exam_personnel_id)
            ->where('examination_school_id', $assignment->examination_school_id)
            ->first();

        if ($validated['present']) {
            if ($attendance === null) {
                NepAttendance::create([
                    'non_exam_personnel_id' => $assignment->non_exam_personnel_id,
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
