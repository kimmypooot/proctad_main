<?php

namespace App\Http\Controllers;

use App\Models\ExaminationSchool;
use App\Models\ScannerSession;
use App\Support\BrandedQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Issues and revokes the public /scan/{token} links. See ScannerSession for
 * why the link is scoped and expiring rather than a permanently open page.
 */
class ScannerSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ScannerSession::class);

        $validated = $request->validate([
            'examination_id' => ['required_without:training_id', 'nullable', 'integer', 'exists:examinations,id'],
            'training_id' => ['required_without:examination_id', 'nullable', 'integer', 'exists:trainings,id'],
            // Required for an examination: OEP and covered-school attendance are
            // both recorded per venue, so a venue-less link would only ever be
            // able to confirm directly-assigned members. Trainings have no
            // venues and leave this null.
            'examination_school_id' => [
                'required_with:examination_id',
                'nullable',
                'integer',
                Rule::exists('examination_school', 'id')
                    ->where('examination_id', $request->integer('examination_id')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            // Capped at a week: these links are meant for an examination day,
            // and an unbounded expiry would quietly recreate the open page this
            // design exists to avoid.
            'expires_at' => ['required', 'date', 'after:now', 'before:'.now()->addWeek()->toDateTimeString()],
        ]);

        $user = $request->user();

        // A venue belongs to a Testing Center; an FO-scoped issuer must not be
        // able to hand out a link into someone else's.
        if ($venueId = $validated['examination_school_id'] ?? null) {
            $venue = ExaminationSchool::with('school')->findOrFail($venueId);

            abort_if(
                $user->role->isFieldOfficeScoped() && $user->field_office_id !== $venue->school?->field_office_id,
                403,
            );
        }

        ScannerSession::create([
            ...$validated,
            'token' => ScannerSession::generateToken(),
            'field_office_id' => $user->field_office_id,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Scanner link created.');
    }

    public function revoke(Request $request, ScannerSession $scannerSession): RedirectResponse
    {
        Gate::authorize('revoke', $scannerSession);

        $scannerSession->update(['revoked_at' => now()]);

        return back()->with('success', 'Scanner link revoked.');
    }

    /**
     * Panel payload for the examination/training show pages: the live links
     * with their full URL and a scannable QR of that URL, so venue staff can
     * point a phone at the admin's screen instead of typing it.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function panelData(string $relation, int $id): array
    {
        return ScannerSession::active()
            ->where($relation, $id)
            ->with(['creator:id,name', 'examinationSchool.school:id,name'])
            ->latest()
            ->get()
            ->map(fn (ScannerSession $session) => [
                'id' => $session->id,
                'label' => $session->label,
                'venue' => $session->examinationSchool?->school?->name,
                'url' => route('scan', $session->token),
                'qr' => BrandedQrCode::dataUri(route('scan', $session->token)),
                'expires_at' => $session->expires_at->format('M d, Y H:i'),
                'last_used_at' => $session->last_used_at?->format('M d, Y H:i'),
                'scan_count' => $session->scan_count,
                'issued_by' => $session->creator?->name,
            ])
            ->all();
    }
}
