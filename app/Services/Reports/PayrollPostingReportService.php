<?php

namespace App\Services\Reports;

use App\Enums\AssignmentStatus;
use App\Enums\PayeeType;
use App\Models\ExamAssignment;
use App\Models\ExaminationSchool;
use App\Models\Examination;
use App\Models\FeeSchedule;
use App\Models\NepAssignment;
use App\Models\Signatory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll disbursement acknowledgment/certification form, generated from the
 * "Payroll Posting.xlsx" template (sheet "Payroll Posting"): an acknowledgment
 * paragraph (A13, merged A13:E16), a 10-row pre-numbered roster block (rows
 * 19-28, expanded via row insertion if the roster is larger), a certification
 * block with a computed total (D29, merged D29:E32), and a signatory block
 * (D36/D37). Scoped to a single testing center, since the acknowledgment text
 * and signatory are venue/field-office specific.
 */
class PayrollPostingReportService
{
    private const ROSTER_DATA_START = 19;

    private const ROSTER_DATA_END = 28;

    private const ACKNOWLEDGMENT_ROW = 13;

    private const CERTIFICATION_ROW = 29;

    private const SIGNATORY_NAME_ROW = 36;

    private const SIGNATORY_POSITION_ROW = 37;

    private const COLUMNS = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly TemplateExcelService $excel)
    {
    }

    /**
     * @return array{blocking: string[], warnings: string[]}
     */
    public function precheck(Examination $examination, ?int $venueId): array
    {
        $blocking = [];

        if (! $venueId) {
            $blocking[] = 'Select a testing center before generating Payroll Posting.';

            return ['blocking' => $blocking, 'warnings' => []];
        }

        $roster = $this->buildRoster($examination, $venueId, $blocking);

        if (empty($blocking) && $roster->isEmpty()) {
            $blocking[] = 'No confirmed roster found for this examination and testing center.';
        }

        if (empty($blocking) && ! $this->resolveSignatory($venueId)) {
            $blocking[] = 'No active signatory configured for this testing center or region-wide default. Configure one under Configuration > Signatories.';
        }

        return ['blocking' => $blocking, 'warnings' => []];
    }

    public function build(Examination $examination, ?int $venueId): BinaryFileResponse
    {
        if (! $venueId) {
            throw new ReportPreconditionException('Select a testing center before generating Payroll Posting.');
        }

        $blocking = [];
        $roster = $this->buildRoster($examination, $venueId, $blocking);

        if (! empty($blocking)) {
            throw new ReportPreconditionException(implode(' ', $blocking));
        }

        if ($roster->isEmpty()) {
            throw new ReportPreconditionException('No confirmed roster found for this examination and testing center.');
        }

        $signatory = $this->resolveSignatory($venueId);

        if (! $signatory) {
            throw new ReportPreconditionException(
                'No active signatory configured for this testing center or region-wide default. Configure one under Configuration > Signatories.'
            );
        }

        $this->excel->load(config('reports.templates.payroll_posting'), 'Payroll Posting');

        $capacity = self::ROSTER_DATA_END - self::ROSTER_DATA_START + 1;
        $extraNeeded = max(0, $roster->count() - $capacity);

        if ($extraNeeded > 0) {
            $this->excel->insertRowsBefore(self::ROSTER_DATA_END + 1, $extraNeeded);
        }

        // Defensive: clear the roster block before writing in case the template
        // carries residual data from a past exam, matching the other report builders.
        $this->excel->clearRows(self::ROSTER_DATA_START, self::ROSTER_DATA_END, self::COLUMNS);
        $this->excel->writeRows($roster->all(), self::ROSTER_DATA_START, self::ROSTER_DATA_END, self::COLUMNS);

        $offset = $extraNeeded;
        $total = (float) $roster->sum('D');

        $this->excel->setCell('A'.self::ACKNOWLEDGMENT_ROW, sprintf(
            'WE hereby acknowledge to have received from %s of the Civil Service Commission, the sum herein specified opposite our respective names, representing payment for service rendered in connection with the conduct of the %s Examination on %s',
            $signatory->name,
            $examination->title,
            $examination->exam_date->format('F j, Y'),
        ));

        $this->excel->setCell('D'.(self::CERTIFICATION_ROW + $offset), sprintf(
            'I HEREBY CERTIFY THAT I have paid to each of the above employees the amount set opposite their names, aggregating to the sum: Php %s',
            number_format($total, 2),
        ));

        $this->excel->setCell('D'.(self::SIGNATORY_NAME_ROW + $offset), $signatory->name);
        $this->excel->setCell('D'.(self::SIGNATORY_POSITION_ROW + $offset), $signatory->position);

        $filename = sprintf('payroll-posting-%s-%s.xlsx', Str::slug($examination->title), now()->format('Ymd_His'));

        return $this->excel->download($filename);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRoster(Examination $examination, int $venueId, array &$blocking): Collection
    {
        $assignments = ExamAssignment::query()
            ->with('member:id,first_name,middle_name,last_name,suffix')
            ->where('examination_id', $examination->id)
            ->where('examination_school_id', $venueId)
            ->where('status', AssignmentStatus::Confirmed)
            ->get();

        $nepAssignments = NepAssignment::query()
            ->with('personnel:id,first_name,middle_name,last_name,suffix,personnel_type')
            ->where('examination_school_id', $venueId)
            ->get();

        $rates = FeeSchedule::allRatesIndexed();
        $missingRoles = [];

        $rateFor = function (PayeeType $type, string $value, string $label) use ($rates, &$missingRoles): int {
            $key = "{$type->value}:{$value}";
            $cents = $rates->get($key);

            if (! $cents) {
                $missingRoles[$label] = true;

                return 0;
            }

            return $cents;
        };

        $roster = collect();
        $running = 0;

        foreach ($assignments as $assignment) {
            $running++;
            $cents = $rateFor(PayeeType::ExamRole, $assignment->role->value, $assignment->role->label());
            $roster->push([
                'A' => $running,
                'B' => $assignment->member?->name,
                'C' => $assignment->role->label(),
                'D' => $cents / 100,
            ]);
        }

        foreach ($nepAssignments as $nepAssignment) {
            $type = $nepAssignment->personnel?->personnel_type;

            if (! $type) {
                continue;
            }

            $running++;
            $cents = $rateFor(PayeeType::PersonnelType, $type->value, $type->label());
            $roster->push([
                'A' => $running,
                'B' => $nepAssignment->personnel?->name,
                'C' => $type->label(),
                'D' => $cents / 100,
            ]);
        }

        if (! empty($missingRoles)) {
            $blocking[] = 'Set a fee rate for: '.implode(', ', array_keys($missingRoles)).' before generating Payroll Posting.';
        }

        return $roster;
    }

    private function resolveSignatory(int $venueId): ?Signatory
    {
        $fieldOfficeId = ExaminationSchool::query()
            ->with('school:id,field_office_id')
            ->find($venueId)
            ?->school
            ?->field_office_id;

        return Signatory::currentFor($fieldOfficeId);
    }
}
