<?php

namespace App\Http\Controllers;

use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches a dual-role user between the staff console and their own PROCTAD
 * pages. A view toggle on one identity — see App\Support\Workspace for why
 * this is deliberately not two accounts, and why it grants nothing.
 */
class WorkspaceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace' => ['required', Rule::in([Workspace::STAFF, Workspace::MEMBER])],
        ]);

        // Switching *to* member requires an accreditation; switching back to
        // staff requires a staff role. Neither is a permission check — it just
        // stops the session naming a workspace the user has no pages for.
        $allowed = $validated['workspace'] === Workspace::MEMBER
            ? Workspace::availableTo($request->user())
            : (bool) $request->user()->role?->isStaff();

        abort_unless($allowed, 403);

        $request->session()->put(Workspace::SESSION_KEY, $validated['workspace']);

        return redirect()->route('dashboard');
    }
}
