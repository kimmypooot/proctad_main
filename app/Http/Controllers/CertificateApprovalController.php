<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\FieldOffice;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CertificateApprovalController extends Controller
{
    /**
     * The approval queue: Field Directors see all pending certificate types
     * for their own Field Office — Appearance/Designation Order as the
     * primary approver, plus Appreciation as a local fallback for when
     * Management isn't available. Management sees pending Appreciation
     * certificates region-wide (spec 2.3 / 4.1 / 4.2). Super Admin/ESD Admin
     * see everything region-wide too, as the region-wide fallback.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $regionWide = $user->role->isRegionWide();

        $types = Certificate::approvableTypesFor($user);

        abort_if($types === [], 403);

        // Scoping lives on the model so the sidebar badge counts exactly what
        // this queue lists — see Certificate::scopePendingApprovalFor().
        $base = Certificate::query()->pendingApprovalFor($user);

        $pending = (clone $base)
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'fieldOffice:id,name,code', 'requestedBy:id,name', 'certifiable')
            // Oldest first: the queue should drain in the order people have
            // been waiting, not bury older requests under newer ones.
            ->oldest('id')
            ->get()
            ->map(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'type' => $certificate->type->value,
                'type_label' => $certificate->type->label(),
                'member' => [
                    'id' => $certificate->member->id,
                    'proctad_id' => $certificate->member->proctad_id,
                    'name' => $certificate->member->name,
                ],
                'field_office_id' => $certificate->field_office_id,
                'field_office' => $certificate->fieldOffice?->name,
                'source' => $certificate->sourceDescription(),
                'source_date' => $certificate->sourceDate(),
                'requested_by' => $certificate->requestedBy?->name ?? 'System',
                // Approving your own request is allowed — a Field Director must be
                // able to cover for an absent FO Admin — but it should be a
                // conscious act, not something that happens without being noticed.
                'self_requested' => $certificate->requested_by === $user->id,
                'requested_at' => $certificate->created_at->format('M d, Y H:i'),
                'requested_ago' => $certificate->created_at->diffForHumans(),
                // diffInDays() returns a float (whole + fractional days) as
                // of Carbon 3 — round to 2 decimal places for display.
                'waiting_days' => round($certificate->created_at->diffInDays(now()), 2),
            ]);

        return Inertia::render('Approvals/Index', [
            'pending' => $pending,
            'types' => collect($types)->map(fn (CertificateType $type) => ['value' => $type->value, 'label' => $type->label()])->values(),
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
        ]);
    }

    public function approve(Request $request, Certificate $certificate, CertificateService $certificates): RedirectResponse
    {
        Gate::authorize('decide', $certificate);

        $certificates->release($certificate, $request->user());

        return back()->with('success', "{$certificate->type->label()} {$certificate->certificate_no} approved, released, and emailed to {$certificate->member->name}.");
    }

    public function disapprove(Request $request, Certificate $certificate, CertificateService $certificates): RedirectResponse
    {
        Gate::authorize('decide', $certificate);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $certificates->disapprove($certificate, $request->user(), $validated['remarks']);

        return back()->with('success', 'Certificate request disapproved.');
    }

    /**
     * Batch-approve N pending requests in one action. Certificates no longer
     * eligible by the time this runs (already decided, or outside this
     * user's authority) are silently skipped rather than failing the batch.
     */
    public function bulkApprove(Request $request, CertificateService $certificates): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['integer', Rule::exists('certificates', 'id')],
        ]);

        $approved = 0;
        $skipped = 0;

        Certificate::whereIn('id', $validated['certificate_ids'])->get()->each(function (Certificate $certificate) use (&$approved, &$skipped, $user, $certificates) {
            if (! $user->can('decide', $certificate)) {
                $skipped++;

                return;
            }

            $certificates->release($certificate, $user);
            $approved++;
        });

        $message = "Approved and released {$approved} certificate(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} no longer eligible.";
        }

        return back()->with('success', $message);
    }

    /**
     * Batch-disapprove N pending requests with one shared reason.
     */
    public function bulkDisapprove(Request $request, CertificateService $certificates): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['integer', Rule::exists('certificates', 'id')],
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $disapproved = 0;
        $skipped = 0;

        Certificate::whereIn('id', $validated['certificate_ids'])->get()->each(function (Certificate $certificate) use (&$disapproved, &$skipped, $user, $certificates, $validated) {
            if (! $user->can('decide', $certificate)) {
                $skipped++;

                return;
            }

            $certificates->disapprove($certificate, $user, $validated['remarks']);
            $disapproved++;
        });

        $message = "Disapproved {$disapproved} certificate(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} no longer eligible.";
        }

        return back()->with('success', $message);
    }
}
