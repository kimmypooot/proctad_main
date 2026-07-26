<?php

namespace App\Services;

use App\Enums\ExamRole;
use App\Enums\PerformanceRating;
use App\Models\Evaluation;
use App\Models\ExamAssignment;
use Illuminate\Support\Collection;

/**
 * Derives a Room Examiner/Proctor's performance rating from the Supervising
 * Examiner's evaluation of them, instead of the manual staff-entered
 * ExamAssignment.performance_rating column. Room Examiner/Proctor are the
 * only roles individually scored by someone else in this system (via
 * Evaluation.room_ratings, a JSON array with no clean Eloquent relation into
 * it) — Chief Examiner/Supervising Examiner have no such data source and
 * keep manual-only ratings.
 *
 * Never writes to performance_rating — callers combine this with the manual
 * column at read time (computed ?? manual) so a staff override is never
 * silently clobbered.
 */
class PerformanceRatingCalculator
{
    /** @var array<int, array<int, array>> examination_id => flattened room_ratings entries, memoized per instance */
    private array $entriesByExamination = [];

    /**
     * @return array{rating: PerformanceRating, average: float, ratings_count: int}|null
     */
    public function computeFor(ExamAssignment $assignment): ?array
    {
        if (! $this->isEligible($assignment)) {
            return null;
        }

        $entries = $this->entriesFor($assignment->examination_id)
            ->filter(fn (array $entry) => (int) ($entry['exam_assignment_id'] ?? 0) === $assignment->id);

        return $this->summarize($entries);
    }

    /**
     * Batch variant for a collection of assignments (may span multiple
     * examinations) — issues one Evaluation query per distinct
     * examination_id rather than one per assignment.
     *
     * @param  Collection<int, ExamAssignment>  $assignments
     * @return array<int, array{rating: PerformanceRating, average: float, ratings_count: int}> keyed by assignment id
     */
    public function computeForMany(Collection $assignments): array
    {
        $results = [];

        foreach ($assignments as $assignment) {
            if (! $this->isEligible($assignment)) {
                continue;
            }

            $entries = $this->entriesFor($assignment->examination_id)
                ->filter(fn (array $entry) => (int) ($entry['exam_assignment_id'] ?? 0) === $assignment->id);

            if ($summary = $this->summarize($entries)) {
                $results[$assignment->id] = $summary;
            }
        }

        return $results;
    }

    private function isEligible(ExamAssignment $assignment): bool
    {
        return $assignment->role->isAnyOf([ExamRole::Proctor, ExamRole::RoomExaminer]);
    }

    /** @return Collection<int, array> */
    private function entriesFor(int $examinationId): Collection
    {
        if (! isset($this->entriesByExamination[$examinationId])) {
            $this->entriesByExamination[$examinationId] = Evaluation::query()
                ->where('examination_id', $examinationId)
                ->where('designation', ExamRole::SupervisingExaminer->value)
                ->get(['id', 'room_ratings'])
                ->flatMap(fn (Evaluation $evaluation) => $evaluation->room_ratings ?? [])
                ->all();
        }

        return collect($this->entriesByExamination[$examinationId]);
    }

    /**
     * @param  Collection<int, array>  $entries
     * @return array{rating: PerformanceRating, average: float, ratings_count: int}|null
     */
    private function summarize(Collection $entries): ?array
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $scores = $entries->flatMap(fn (array $entry) => [
            ...($entry['punctuality'] ?? []),
            ...($entry['decorum'] ?? []),
            ...($entry['procedures'] ?? []),
        ])->filter(fn ($value) => is_numeric($value));

        if ($scores->isEmpty()) {
            return null;
        }

        $average = round($scores->avg(), 2);

        return [
            'rating' => $this->mapToEnum($average),
            'average' => $average,
            'ratings_count' => $entries->count(),
        ];
    }

    private function mapToEnum(float $average): PerformanceRating
    {
        $rounded = max(1, min(5, (int) round($average)));

        return match ($rounded) {
            5 => PerformanceRating::Outstanding,
            4 => PerformanceRating::VerySatisfactory,
            3 => PerformanceRating::Satisfactory,
            2 => PerformanceRating::Unsatisfactory,
            default => PerformanceRating::Poor,
        };
    }
}
