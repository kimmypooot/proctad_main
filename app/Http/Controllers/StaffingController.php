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
            'rooms_per_supervisor' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if ($venue->rooms()->doesntExist()) {
            return back()->with('error', 'No rooms found for this venue. Add rooms first.');
        }

        $summary = $randomizer->randomize($venue, $validated['scope'], $validated['rooms_per_supervisor'] ?? 5);
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
        $venue->loadMissing('school');

        abort_unless(
            $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)
                || ($user->role->isFieldOfficeScoped() && $user->field_office_id === $venue->school?->field_office_id),
            403,
        );
    }
}
