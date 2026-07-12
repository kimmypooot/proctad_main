<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CertificateApprovalController extends Controller
{
    /**
     * The approval queue: Field Directors see pending Appearance certificates
     * and Designation Orders of their own FO; Management sees pending
     * Appreciation certificates region-wide (spec 2.3 / 4.1 / 4.2).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $types = match ($user->role) {
            UserRole::FieldDirector => [CertificateType::Appearance, CertificateType::DesignationOrder],
            UserRole::Management => [CertificateType::Appreciation],
            UserRole::SuperAdmin => [CertificateType::Appearance, CertificateType::DesignationOrder, CertificateType::Appreciation],
            default => abort(403),
        };

        $pending = Certificate::with('member:id,proctad_id,first_name,middle_name,last_name,suffix',
            'fieldOffice:id,name,code', 'requestedBy:id,name', 'certifiable')
            ->where('status', CertificateStatus::Pending)
            ->whereIn('type', $types)
            ->when($user->role === UserRole::FieldDirector,
                fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->latest('id')
            ->get()
            ->map(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'type_label' => $certificate->type->label(),
                'member' => [
                    'id' => $certificate->member->id,
                    'proctad_id' => $certificate->member->proctad_id,
                    'name' => $certificate->member->name,
                ],
                'field_office' => $certificate->fieldOffice?->name,
                'source' => $certificate->sourceDescription(),
                'source_date' => $certificate->sourceDate(),
                'requested_by' => $certificate->requestedBy?->name ?? 'System',
                'requested_at' => $certificate->created_at->format('M d, Y H:i'),
            ]);

        return Inertia::render('Approvals/Index', ['pending' => $pending]);
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
}
