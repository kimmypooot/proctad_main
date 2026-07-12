<?php

namespace App\Http\Controllers;

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
            return $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }
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
            return $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }
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
            return $service->build($examination, $venueId);
        } catch (ReportPreconditionException $e) {
            return back()->with('error', $e->getMessage());
        }
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

    /**
     * @param  array{blocking: string[], warnings: string[]}  $result
     */
    private function precheckResponse(array $result): JsonResponse
    {
        return response()->json(['ok' => empty($result['blocking']), ...$result]);
    }
}
