<?php

namespace Tests\Unit;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Services\RoomStaffingCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * RoomStaffingCalculator takes collections and returns arrays — it never
 * queries. These cases therefore run without booting the framework or the
 * database, which is what makes it cheap to cover the arithmetic edges
 * (anchor-group boundaries, the ratio cap, empty venues) that the
 * database-backed Feature test does not reach.
 */
class RoomStaffingCalculatorTest extends TestCase
{
    private RoomStaffingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // Passed in rather than read from the registry, which keeps these cases
        // free of the database and the framework. These are the three the
        // migration seeds, so the arithmetic under test is the real one.
        $this->calculator = new RoomStaffingCalculator([
            ['key' => 'proctor', 'label' => 'Proctor', 'rooms_per_slot' => 1, 'is_anchored' => false],
            ['key' => 'room_examiner', 'label' => 'Room Examiner', 'rooms_per_slot' => 1, 'is_anchored' => false],
            [
                'key' => 'supervising_examiner',
                'label' => 'Supervising Examiner',
                'rooms_per_slot' => RoomStaffingCalculator::ROOMS_PER_SUPERVISOR,
                'is_anchored' => true,
            ],
        ]);
    }

    private function rooms(int $count, int $capacity = 25): Collection
    {
        return collect(range(1, $count))->map(fn (int $i) => (object) [
            'id' => $i,
            'room_number' => sprintf('Room-%03d', $i),
            'capacity' => $capacity,
            'designation' => null,
        ]);
    }

    private function assignment(int $id, ExamRole $role, ?int $roomId, AssignmentStatus $status = AssignmentStatus::Confirmed): array
    {
        return [
            'id' => $id,
            'role' => $role->value,
            'exam_room_id' => $roomId,
            'status' => $status->value,
            'member_name' => "Member {$id}",
        ];
    }

    /**
     * One Supervising Examiner per five rooms, rounded up — the boundary the
     * anchor-group logic in breakdown() depends on.
     */
    public function test_supervising_examiners_required_rounds_up_per_five_rooms(): void
    {
        foreach ([1 => 1, 4 => 1, 5 => 1, 6 => 2, 10 => 2, 11 => 3] as $roomCount => $expected) {
            $stats = $this->calculator->stats($this->rooms($roomCount), collect());

            $this->assertSame(
                $expected,
                $stats['required']['supervising_examiner'],
                "{$roomCount} rooms should require {$expected} supervising examiner(s)",
            );
            $this->assertSame($roomCount, $stats['required']['proctor']);
            $this->assertSame($roomCount, $stats['required']['room_examiner']);
        }
    }

    public function test_a_venue_with_no_rooms_reports_a_null_ratio_rather_than_dividing_by_zero(): void
    {
        $stats = $this->calculator->stats(collect(), collect());

        $this->assertNull($stats['ratio']);
        $this->assertSame(0, $stats['required_total']);
        $this->assertSame(0, $stats['rooms_count']);
        $this->assertSame(0, $stats['total_capacity']);
    }

    /** Over-staffing shows as 100%, never above — the bar can't overflow. */
    public function test_ratio_is_capped_at_one_hundred_when_overstaffed(): void
    {
        $rooms = $this->rooms(1);
        $assignments = collect([
            $this->assignment(1, ExamRole::Proctor, 1),
            $this->assignment(2, ExamRole::RoomExaminer, 1),
            $this->assignment(3, ExamRole::SupervisingExaminer, 1),
            $this->assignment(4, ExamRole::Proctor, 1),
            $this->assignment(5, ExamRole::Proctor, 1),
        ]);

        $this->assertSame(100, $this->calculator->stats($rooms, $assignments)['ratio']);
    }

    public function test_total_capacity_sums_across_rooms(): void
    {
        $this->assertSame(75, $this->calculator->stats($this->rooms(3, 25), collect())['total_capacity']);
    }

    /**
     * Rooms are ordered by the digits in their number, so Room-010 sorts after
     * Room-002 rather than lexicographically before it. Anchor groups are
     * assigned by position, so a wrong order silently moves supervisors.
     */
    public function test_rooms_are_ordered_numerically_not_lexicographically(): void
    {
        $rooms = collect([
            (object) ['id' => 1, 'room_number' => 'Room-10', 'capacity' => 25, 'designation' => null],
            (object) ['id' => 2, 'room_number' => 'Room-2', 'capacity' => 25, 'designation' => null],
            (object) ['id' => 3, 'room_number' => 'Room-1', 'capacity' => 25, 'designation' => null],
        ]);

        $ordered = $this->calculator->breakdown($rooms, collect())->pluck('room_number')->all();

        $this->assertSame(['Room-1', 'Room-2', 'Room-10'], $ordered);
    }

    /**
     * A Supervising Examiner is stored against the group's anchor room only,
     * but every room in that group of five is staffed by them.
     */
    public function test_supervisor_is_credited_to_every_room_in_its_anchor_group_but_not_the_next(): void
    {
        $rooms = $this->rooms(6);
        $assignments = collect([
            $this->assignment(1, ExamRole::SupervisingExaminer, 1),
        ]);

        $breakdown = $this->calculator->breakdown($rooms, $assignments);

        // Rooms 1-5 form the first anchor group; room 6 starts the second.
        foreach (range(0, 4) as $index) {
            $this->assertSame('Member 1', $breakdown[$index]['supervising_examiner']);
        }
        $this->assertNull($breakdown[5]['supervising_examiner']);

        $this->assertTrue($breakdown[0]['is_supervisor_anchor']);
        $this->assertFalse($breakdown[1]['is_supervisor_anchor']);
        $this->assertTrue($breakdown[5]['is_supervisor_anchor']);
    }

    public function test_a_room_is_complete_only_with_all_three_roles(): void
    {
        $rooms = $this->rooms(1);

        $partial = collect([
            $this->assignment(1, ExamRole::Proctor, 1),
            $this->assignment(2, ExamRole::RoomExaminer, 1),
        ]);
        $this->assertFalse($this->calculator->breakdown($rooms, $partial)[0]['complete']);

        $full = $partial->push($this->assignment(3, ExamRole::SupervisingExaminer, 1));
        $this->assertTrue($this->calculator->breakdown($rooms, $full)[0]['complete']);
    }

    /**
     * Declined, cancelled and expired responses leave the slot open — they must
     * not count as staffing anywhere.
     */
    public function test_non_pending_non_confirmed_assignments_are_ignored(): void
    {
        $rooms = $this->rooms(1);

        foreach ([AssignmentStatus::Declined, AssignmentStatus::Cancelled, AssignmentStatus::Expired] as $status) {
            $assignments = collect([$this->assignment(1, ExamRole::Proctor, 1, $status)]);

            $this->assertSame(
                0,
                $this->calculator->stats($rooms, $assignments)['assigned']['proctor'],
                "{$status->value} should not count toward staffing",
            );
            $this->assertNull($this->calculator->breakdown($rooms, $assignments)[0]['proctor']);
        }
    }

    public function test_pending_assignments_do_count(): void
    {
        $rooms = $this->rooms(1);
        $assignments = collect([$this->assignment(1, ExamRole::Proctor, 1, AssignmentStatus::Pending)]);

        $this->assertSame(1, $this->calculator->stats($rooms, $assignments)['assigned']['proctor']);
    }

    /** An assignment with no room yet is not placed, so it is not staffing a room. */
    public function test_unplaced_assignments_do_not_count_toward_assigned_totals(): void
    {
        $rooms = $this->rooms(1);
        $assignments = collect([$this->assignment(1, ExamRole::Proctor, null)]);

        $this->assertSame(0, $this->calculator->stats($rooms, $assignments)['assigned_total']);
    }
}
