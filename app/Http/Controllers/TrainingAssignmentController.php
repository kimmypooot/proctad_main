<?php

namespace App\Http\Controllers;

use App\Enums\BlacklistStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TrainingAssignmentController extends Controller
{
    public function store(Request $request, Training $training): RedirectResponse
    {
        Gate::authorize('create', TrainingAssignment::class);

        $user = $request->user();

        $validated = $request->validate([
            'member_id' => [
                'required',
                Rule::exists('members', 'id'),
                Rule::unique('training_assignments', 'member_id')
                    ->where('training_id', $training->id),
            ],
        ], [
            'member_id.unique' => 'This member is already assigned to this training.',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        abort_if(
            $user->role->isFieldOfficeScoped() && ! $member->isWithinJurisdictionOf($user),
            403,
        );

        abort_if(
            $member->blacklists()->where('status', BlacklistStatus::Active)->exists(),
            422,
            'This member is blacklisted and cannot be assigned.',
        );

        $training->assignments()->create([
            'member_id' => $member->id,
            'field_office_id' => $member->field_office_id,
        ]);

        return back()->with('success', "{$member->name} assigned to {$training->title}.");
    }

    public function update(Request $request, TrainingAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate([
            'attended' => ['required', 'boolean'],
        ]);

        $assignment->update([
            'attendance_confirmed_at' => $validated['attended']
                ? ($assignment->attendance_confirmed_at ?? now())
                : null,
            'attendance_confirmed_by' => $validated['attended']
                ? ($assignment->attendance_confirmed_by ?? $request->user()->id)
                : null,
        ]);

        return back()->with('success', 'Training record updated.');
    }

    public function destroy(TrainingAssignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }
}
