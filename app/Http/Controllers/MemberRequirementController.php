<?php

namespace App\Http\Controllers;

use App\Enums\EligibilityRequirement;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberRequirementController extends Controller
{
    public function update(Request $request, Member $member, string $requirement): RedirectResponse
    {
        Gate::authorize('update', $member);

        $key = EligibilityRequirement::tryFrom($requirement) ?? abort(404);

        $validated = $request->validate([
            'complied' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:5120', Rule::file()->types(['pdf', 'jpg', 'jpeg', 'png'])],
        ]);

        $record = $member->requirements()->firstOrCreate(['requirement' => $key]);

        if ($request->hasFile('file')) {
            if ($record->file_path) {
                Storage::disk('local')->delete($record->file_path);
            }
            $validated['file_path'] = $request->file('file')
                ->store("member-requirements/{$member->id}", 'local');
        }

        unset($validated['file']);
        $record->update($validated);

        return back()->with('success', 'Eligibility requirement updated.');
    }

    public function download(Member $member, string $requirement): StreamedResponse
    {
        Gate::authorize('view', $member);

        $key = EligibilityRequirement::tryFrom($requirement) ?? abort(404);

        $record = $member->requirements()
            ->where('requirement', $key)
            ->firstOrFail();

        abort_unless($record->file_path && Storage::disk('local')->exists($record->file_path), 404);

        $extension = pathinfo($record->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $record->file_path,
            "{$member->proctad_id}-{$requirement}.{$extension}",
        );
    }
}
