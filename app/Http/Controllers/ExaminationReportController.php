<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Examination;
use App\Services\Reports\PayrollPostingReportService;
use App\Services\Reports\PayrollReportService;
use App\Services\Reports\ReportPreconditionException;
use App\Services\Reports\RoomAssignmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExaminationReportController extends Controller
{
    public function roomAssignment(Request $request, Examination $examination, RoomAssignmentReportService $service): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        try {
            $response = $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->logReportGenerated($request, $examination, 'room_assignment');

        return $response;
    }

    public function roomAssignmentPrecheck(Request $request, Examination $examination, RoomAssignmentReportService $service): JsonResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        return $this->precheckResponse($service->precheck($examination, $venueId));
    }

    public function payroll(Request $request, Examination $examination, PayrollReportService $service): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        try {
            $response = $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->logReportGenerated($request, $examination, 'payroll');

        return $response;
    }

    public function payrollPrecheck(Request $request, Examination $examination, PayrollReportService $service): JsonResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        return $this->precheckResponse($service->precheck($examination, $venueId));
    }

    public function payrollPosting(Request $request, Examination $examination, PayrollPostingReportService $service): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        try {
            $response = $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->logReportGenerated($request, $examination, 'payroll_posting');

        return $response;
    }

    public function payrollPostingPrecheck(Request $request, Examination $examination, PayrollPostingReportService $service): JsonResponse
    {
        Gate::authorize('view', $examination);
        $venueId = $this->validatedVenueId($request, $examination);

        return $this->precheckResponse($service->precheck($examination, $venueId));
    }

    private function validatedVenueId(Request $request, Examination $examination): ?int
    {
        $validated = $request->validate([
            'venue_id' => ['nullable', 'integer', Rule::exists('examination_school', 'id')->where('examination_id', $examination->id)],
        ]);

        return isset($validated['venue_id']) ? (int) $validated['venue_id'] : null;
    }

    /** Drives the "Generate Reports" step's completion indicator on the wizard — see ExaminationController::show(). */
    private function logReportGenerated(Request $request, Examination $examination, string $reportType): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'report_generated',
            'auditable_type' => Examination::class,
            'auditable_id' => $examination->id,
            'field_office_id' => $request->user()->field_office_id,
            'changes' => ['report_type' => $reportType],
        ]);
    }

    /**
     * @param  array{blocking: string[], warnings: string[]}  $result
     */
    private function precheckResponse(array $result): JsonResponse
    {
        return response()->json(['ok' => empty($result['blocking']), ...$result]);
    }
}
