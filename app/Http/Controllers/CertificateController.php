<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Signatory;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Certificate::class);

        $user = $request->user();

        $foScoped = $user->role->isFieldOfficeScoped();

        $baseQuery = Certificate::query()
            ->when($foScoped, fn ($q) => $q->where('field_office_id', $user->field_office_id));

        $certificates = (clone $baseQuery)
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix',
                'fieldOffice:id,name,code', 'certifiable')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('certificate_no', 'like', "%{$term}%")
                    ->orWhereHas('member', fn ($m) => $m
                        ->where('proctad_id', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'certificate_no' => $certificate->certificate_no,
                'type_label' => $certificate->type->label(),
                'member' => [
                    'id' => $certificate->member->id,
                    'name' => $certificate->member->name,
                    'proctad_id' => $certificate->member->proctad_id,
                ],
                'field_office' => $certificate->fieldOffice?->name,
                'source' => $certificate->sourceDescription(),
                'status' => $certificate->status->value,
                'status_label' => $certificate->status->label(),
                'status_variant' => $certificate->status->badgeVariant(),
                'disapproval_remarks' => $certificate->disapproval_remarks,
                'released_at' => $certificate->released_at?->format('M d, Y'),
                'downloadable' => $user->can('download', $certificate),
            ]);

        return Inertia::render('Certificates/Index', [
            'certificates' => $certificates,
            'filters' => $request->only('search', 'status', 'type'),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'released' => (clone $baseQuery)->where('status', CertificateStatus::Released)->count(),
                'pending' => (clone $baseQuery)->where('status', CertificateStatus::Pending)->count(),
                'disapproved' => (clone $baseQuery)->where('status', CertificateStatus::Disapproved)->count(),
            ],
            'types' => collect(CertificateType::cases())
                ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])->all(),
            'signatories' => Signatory::where('active', true)->orderBy('name')->get(['id', 'name', 'position']),
            'can' => ['bulkResign' => $user->role === UserRole::SuperAdmin],
        ]);
    }

    /**
     * Superadmin-only: swap the signatory on a batch of released certificates
     * and regenerate their PDFs. Ported from bulk-resign-certificates.php.
     */
    public function bulkResign(Request $request, CertificateService $certificates): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::SuperAdmin, 403);

        $validated = $request->validate([
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['integer', Rule::exists('certificates', 'id')],
            'signatory_id' => ['required', Rule::exists('signatories', 'id')->where('active', true)],
        ]);

        $signatory = Signatory::findOrFail($validated['signatory_id']);

        $updated = 0;
        $skipped = 0;

        Certificate::whereIn('id', $validated['certificate_ids'])->get()->each(function (Certificate $certificate) use (&$updated, &$skipped, $signatory, $certificates) {
            if ($certificate->status !== CertificateStatus::Released) {
                $skipped++;

                return;
            }

            $certificates->resign($certificate, $signatory);
            $updated++;
        });

        return back()->with('success', "Re-signed {$updated} certificate(s)."
            .($skipped > 0 ? " Skipped {$skipped} not yet released." : ''));
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        Gate::authorize('download', $certificate);

        abort_unless($certificate->pdf_path && Storage::disk('local')->exists($certificate->pdf_path), 404);

        return Storage::disk('local')->download(
            $certificate->pdf_path,
            "{$certificate->certificate_no}.pdf",
        );
    }

    /**
     * Inline preview of the released PDF (for the "View" modal), same
     * authorization as download() but rendered in the browser instead of
     * forcing an attachment.
     */
    public function view(Certificate $certificate): StreamedResponse
    {
        Gate::authorize('download', $certificate);

        abort_unless($certificate->pdf_path && Storage::disk('local')->exists($certificate->pdf_path), 404);

        return Storage::disk('local')->response(
            $certificate->pdf_path,
            "{$certificate->certificate_no}.pdf",
        );
    }
}
