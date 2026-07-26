<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ExamAssignment;
use App\Models\ExaminationSchool;
use App\Models\User;
use App\Services\StaffingRandomizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StaffingController extends Controller
{
    public function randomize(Request $request, ExaminationSchool $venue, StaffingRandomizer $randomizer): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);
        $this->authorizeForVenue($request->user(), $venue);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['all', 'unfilled'])],
            // A Supervising Examiner covers between 3 and 8 rooms, as the field
            // office decides for the venue.
            'rooms_per_supervisor' => [
                'nullable', 'integer',
                'min:'.ExaminationSchool::MIN_ROOMS_PER_SUPERVISOR,
                'max:'.ExaminationSchool::MAX_ROOMS_PER_SUPERVISOR,
            ],
        ]);

        if ($venue->rooms()->doesntExist()) {
            return back()->with('error', 'No rooms found for this venue. Add rooms first.');
        }

        $roomsPerSupervisor = $validated['rooms_per_supervisor'] ?? $venue->roomsPerSupervisor();

        // Persisted before staffing, so the grid groups rooms exactly the way
        // the randomizer is about to. Leaving it unsaved is what let the two
        // disagree in the first place.
        $venue->update(['rooms_per_supervisor' => $roomsPerSupervisor]);

        $summary = $randomizer->randomize($venue, $validated['scope'], $roomsPerSupervisor);
        $total = array_sum($summary);

        return back()->with('success', $total > 0
            ? "Auto-assignment complete: {$summary['proctors']} proctor(s), {$summary['examiners']} room examiner(s), {$summary['supervisors']} supervising examiner(s) assigned to rooms."
            : 'No eligible staff were available to assign to rooms.');
    }

    public function clear(Request $request, ExaminationSchool $venue, StaffingRandomizer $randomizer): RedirectResponse
    {
        Gate::authorize('create', ExamAssignment::class);
        $this->authorizeForVenue($request->user(), $venue);

        $cleared = $randomizer->clear($venue);

        return back()->with('success', "Room staffing cleared. {$cleared} assignment(s) unlinked from rooms.");
    }

    private function authorizeForVenue(User $user, ExaminationSchool $venue): void
    {
        $venue->loadMissing('school.testingCenter');

        abort_unless(
            $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)
                || ($user->role->isFieldOfficeScoped() && $venue->school?->handledByOffice($user->field_office_id)),
            403,
        );
    }
}
