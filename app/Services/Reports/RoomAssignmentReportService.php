<?php

namespace App\Services\Reports;

use App\Enums\ExamRole;
use App\Models\ExamAssignment;
use App\Models\Examination;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Room-by-room Proctor / Supervising Examiner roster, generated from the
 * Alphalist.xlsx template (sheet "Alphalist LNU"): title row 1 and header
 * row 2 are generic and left untouched, data starts at row 3.
 */
class RoomAssignmentReportService
{
    private const TEMPLATE_LAST_PREFORMATTED_ROW = 974;

    private const COLUMNS = ['B', 'C', 'D', 'E', 'F'];

    public function __construct(private readonly TemplateExcelService $excel)
    {
    }

    /**
     * @return array{blocking: string[], warnings: string[]}
     */
    public function precheck(Examination $examination, ?int $venueId = null): array
    {
        $proctors = $this->proctorAssignments($examination, $venueId);

        $blocking = [];
        $warnings = [];

        if ($proctors->isEmpty()) {
            $blocking[] = 'No confirmed Proctor assignments with a room found for this examination'.($venueId ? ' and testing center.' : '.');
        }

        $supervisingExaminersByRoom = $this->supervisingExaminersByRoom($examination, $proctors);
        $missingSe = $proctors->filter(fn (ExamAssignment $a) => ! $supervisingExaminersByRoom->has($a->exam_room_id))->count();

        if ($missingSe > 0) {
            $warnings[] = "{$missingSe} room(s) have a Proctor but no Supervising Examiner assigned — these will show \"Not Assigned\".";
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    public function build(Examination $examination, ?int $venueId = null): BinaryFileResponse
    {
        $proctors = $this->proctorAssignments($examination, $venueId);

        if ($proctors->isEmpty()) {
            throw new ReportPreconditionException(
                'No confirmed Proctor assignments with a room found for this examination'.($venueId ? ' and testing center.' : '.')
            );
        }

        $supervisingExaminersByRoom = $this->supervisingExaminersByRoom($examination, $proctors);

        $rows = $proctors->values()->map(fn (ExamAssignment $proctor) => [
            'B' => $proctor->member?->name,
            'C' => $proctor->room?->room_number,
            'D' => null,
            'E' => $supervisingExaminersByRoom->get($proctor->exam_room_id)?->member?->name ?? '— Not Assigned —',
            'F' => $proctor->room?->designation,
        ])->all();

        $this->excel
            ->load(config('reports.templates.room_assignment'), 'Alphalist LNU')
            // The template is an actual filled document from a past exam, not a blank
            // form — clear its residual data before writing this exam's roster so no
            // prior exam's personnel information carries over into the new file.
            ->clearRows(3, self::TEMPLATE_LAST_PREFORMATTED_ROW, self::COLUMNS)
            ->writeRows($rows, 3, self::TEMPLATE_LAST_PREFORMATTED_ROW, self::COLUMNS);

        $filename = sprintf('room-assignment-%s-%s.xlsx', Str::slug($examination->title), now()->format('Ymd_His'));

        return $this->excel->download($filename);
    }

    private function proctorAssignments(Examination $examination, ?int $venueId)
    {
        return ExamAssignment::query()
            ->with(['member:id,first_name,middle_name,last_name,suffix', 'room:id,room_number,designation'])
            ->where('examination_id', $examination->id)
            ->where('role', ExamRole::Proctor)
            ->whereNotNull('exam_room_id')
            ->when($venueId, fn ($q) => $q->where('examination_school_id', $venueId))
            ->get();
    }

    private function supervisingExaminersByRoom(Examination $examination, $proctors)
    {
        return ExamAssignment::query()
            ->with('member:id,first_name,middle_name,last_name,suffix')
            ->where('examination_id', $examination->id)
            ->where('role', ExamRole::SupervisingExaminer)
            ->whereIn('exam_room_id', $proctors->pluck('exam_room_id')->filter()->unique())
            ->get()
            ->keyBy('exam_room_id');
    }
}
