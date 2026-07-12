<?php

namespace App\Services\Reports;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FeeSchedule;
use App\Models\NepAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Honorarium payroll sheet, generated from the paginated Payroll.xlsx
 * template (sheet "Payroll SE LNU"): Page 1 (rows 12-50) lists every
 * confirmed role except Room Examiner/Proctor plus Non-Exam Personnel,
 * Page 2 (rows 86-127) lists Room Examiners, Page 3 (rows 160-201) lists
 * Proctors. Each page has its own Page Total; the final Grand Total (row
 * 203 in the unmodified template) is recomputed here as the true sum of
 * all three page totals — the legacy template's formula only summed pages
 * 2 and 3, omitting page 1.
 */
class PayrollReportService
{
    private const PAGE1_DATA_START = 12;

    private const PAGE1_DATA_END = 50;

    private const PAGE1_TOTAL_ROW = 51;

    private const PAGE2_DATA_START = 86;

    private const PAGE2_DATA_END = 127;

    private const PAGE2_TOTAL_ROW = 128;

    private const PAGE3_DATA_START = 160;

    private const PAGE3_DATA_END = 201;

    private const PAGE3_TOTAL_ROW = 202;

    private const GRAND_TOTAL_ROW = 203;

    private const COLUMNS = ['A', 'B', 'C', 'D', 'F'];

    public function __construct(private readonly TemplateExcelService $excel)
    {
    }

    /**
     * @return array{blocking: string[], warnings: string[]}
     */
    public function precheck(Examination $examination, ?int $venueId = null): array
    {
        $blocking = [];

        [$page1, $page2, $page3] = $this->buildRosterRows($examination, $venueId, $blocking);

        if (empty($blocking) && $page1->isEmpty() && $page2->isEmpty() && $page3->isEmpty()) {
            $blocking[] = 'No confirmed roster found for this examination'.($venueId ? ' and testing center.' : '.');
        }

        return ['blocking' => $blocking, 'warnings' => []];
    }

    public function build(Examination $examination, ?int $venueId = null): BinaryFileResponse
    {
        $blocking = [];
        [$page1, $page2, $page3] = $this->buildRosterRows($examination, $venueId, $blocking);

        if (! empty($blocking)) {
            throw new ReportPreconditionException(implode(' ', $blocking));
        }

        if ($page1->isEmpty() && $page2->isEmpty() && $page3->isEmpty()) {
            throw new ReportPreconditionException(
                'No confirmed roster found for this examination'.($venueId ? ' and testing center.' : '.')
            );
        }

        $this->excel->load(config('reports.templates.payroll'), 'Payroll SE LNU');

        $offset = 0;
        $page1Total = $this->writePage($page1, self::PAGE1_DATA_START, self::PAGE1_DATA_END, $offset);
        $page2Total = $this->writePage($page2, self::PAGE2_DATA_START, self::PAGE2_DATA_END, $offset);
        $page3Total = $this->writePage($page3, self::PAGE3_DATA_START, self::PAGE3_DATA_END, $offset);

        $this->excel->setCell('D'.(self::PAGE1_TOTAL_ROW + $offset), $page1Total);
        $this->excel->setCell('E'.(self::PAGE2_TOTAL_ROW + $offset), $page2Total);
        $this->excel->setCell('E'.(self::PAGE3_TOTAL_ROW + $offset), $page3Total);
        $this->excel->setCell('E'.(self::GRAND_TOTAL_ROW + $offset), $page1Total + $page2Total + $page3Total);

        $filename = sprintf('payroll-%s-%s.xlsx', Str::slug($examination->title), now()->format('Ymd_His'));

        return $this->excel->download($filename);
    }

    /**
     * Writes one page's roster rows, expanding the template's pre-formatted
     * block via row insertion if the roster exceeds it. Returns the page's
     * total amount and mutates $offset by the number of rows inserted, so
     * subsequent pages (which sit lower in the same sheet) know how far
     * their own template row numbers have shifted.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function writePage(Collection $rows, int $dataStart, int $dataEnd, int &$offset): float
    {
        $capacity = $dataEnd - $dataStart + 1;
        $extraNeeded = max(0, $rows->count() - $capacity);

        $effectiveDataStart = $dataStart + $offset;
        $effectiveDataEnd = $dataEnd + $offset;

        if ($extraNeeded > 0) {
            $this->excel->insertRowsBefore($effectiveDataEnd + 1, $extraNeeded);
        }

        // The template is an actual filled payroll sheet from a past exam, not a
        // blank form — every pre-formatted row in this page is already populated
        // with real personnel data, so it must be cleared before writing this
        // exam's roster on top of it.
        $this->excel->clearRows($effectiveDataStart, $effectiveDataEnd, self::COLUMNS);
        $this->excel->writeRows($rows->all(), $effectiveDataStart, $effectiveDataEnd, self::COLUMNS);

        $offset += $extraNeeded;

        return (float) $rows->sum('D');
    }

    /**
     * @return array{0: Collection, 1: Collection, 2: Collection} [page1Rows, page2Rows, page3Rows]
     */
    private function buildRosterRows(Examination $examination, ?int $venueId, array &$blocking): array
    {
        $assignments = ExamAssignment::query()
            ->with(['member:id,first_name,middle_name,last_name,suffix', 'room:id,room_number'])
            ->where('examination_id', $examination->id)
            ->where('status', AssignmentStatus::Confirmed)
            ->when($venueId, fn ($q) => $q->where('examination_school_id', $venueId))
            ->get();

        $nepAssignments = NepAssignment::query()
            ->with('personnel:id,first_name,middle_name,last_name,suffix,personnel_type')
            ->whereHas('examinationSchool', fn ($q) => $q->where('examination_id', $examination->id)
                ->when($venueId, fn ($q2) => $q2->where('id', $venueId)))
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

        $page2Assignments = $assignments->where('role', ExamRole::RoomExaminer);
        $page3Assignments = $assignments->where('role', ExamRole::Proctor);
        $page1Assignments = $assignments->whereNotIn('role', [ExamRole::RoomExaminer, ExamRole::Proctor]);

        $page1 = collect();
        $running = 0;

        foreach ($page1Assignments as $assignment) {
            $running++;
            $cents = $rateFor(PayeeType::ExamRole, $assignment->role->value, $assignment->role->label());
            $page1->push([
                'A' => $running,
                'B' => $assignment->member?->name,
                'C' => $assignment->role->label(),
                'D' => $cents / 100,
                'F' => $running,
            ]);
        }

        foreach ($nepAssignments as $nepAssignment) {
            $type = $nepAssignment->personnel?->personnel_type;

            if (! $type) {
                continue;
            }

            $running++;
            $cents = $rateFor(PayeeType::PersonnelType, $type->value, $type->label());
            $page1->push([
                'A' => $running,
                'B' => $nepAssignment->personnel?->name,
                'C' => $type->label(),
                'D' => $cents / 100,
                'F' => $running,
            ]);
        }

        $page2 = $page2Assignments->values()->map(function (ExamAssignment $assignment) use ($rateFor) {
            $cents = $rateFor(PayeeType::ExamRole, ExamRole::RoomExaminer->value, ExamRole::RoomExaminer->label());
            $roomNumber = $assignment->room?->room_number;

            return [
                'A' => $roomNumber,
                'B' => $assignment->member?->name,
                'C' => ExamRole::RoomExaminer->label(),
                'D' => $cents / 100,
                'F' => $roomNumber,
            ];
        });

        $page3 = $page3Assignments->values()->map(function (ExamAssignment $assignment) use ($rateFor) {
            $cents = $rateFor(PayeeType::ExamRole, ExamRole::Proctor->value, ExamRole::Proctor->label());
            $roomNumber = $assignment->room?->room_number;

            return [
                'A' => $roomNumber,
                'B' => $assignment->member?->name,
                'C' => ExamRole::Proctor->label(),
                'D' => $cents / 100,
                'F' => $roomNumber,
            ];
        });

        if (! empty($missingRoles)) {
            $blocking[] = 'Set a fee rate for: '.implode(', ', array_keys($missingRoles)).' before generating Payroll.';
        }

        return [$page1, $page2, $page3];
    }
}
