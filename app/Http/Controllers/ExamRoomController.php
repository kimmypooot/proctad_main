<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Models\ExaminationSchool;
use App\Models\ExamRoom;
use App\Models\User;
use App\Services\RoomStaffingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamRoomController extends Controller
{
    public function __construct(private RoomStaffingCalculator $roomStaffing) {}

    public const DESIGNATIONS = [
        'Professional',
        'Sub-Professional',
        'Fire Officer Examination',
        'Penology Officer Examination',
        'BCLTE',
        'ICLTE',
        'Special Needs',
    ];

    private const ROOM_ROLES = [ExamRole::Proctor->value, ExamRole::RoomExaminer->value, ExamRole::SupervisingExaminer->value];

    public function index(Request $request, ExaminationSchool $venue): Response
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        $venue->loadMissing('school', 'examination');

        // Numeric order (not DB string order) so anchor-group math here always
        // matches StaffingRandomizer's/RoomStaffingCalculator's own ordering.
        $rooms = $venue->rooms()->get()
            ->sortBy(fn (ExamRoom $room) => (int) preg_replace('/\D/', '', $room->room_number) ?: 0)
            ->values();

        $assignments = $venue->assignments()
            ->whereIn('role', self::ROOM_ROLES)
            ->whereIn('status', [AssignmentStatus::Pending->value, AssignmentStatus::Confirmed->value])
            ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'member' => ['id' => $a->member->id, 'name' => $a->member->name],
                'role' => $a->role->value,
                'exam_room_id' => $a->exam_room_id,
                'status' => $a->status->value,
                'member_name' => $a->member->name,
            ]);

        // Assigning staff only ever changes assignments/breakdown/stats, so the
        // staffing map re-requests just those three (see Rooms.vue's `only:`).
        // Everything else is a closure: on a partial reload Inertia never
        // evaluates it, and the client keeps the copy it already has.
        return Inertia::render('Examinations/Rooms', [
            'examination' => fn () => [
                'id' => $venue->examination->id,
                'title' => $venue->examination->title,
                'exam_date' => $venue->examination->exam_date->toDateString(),
            ],
            'venue' => fn () => [
                'id' => $venue->id,
                'school_name' => $venue->school?->name,
                'municipality' => $venue->school?->testingCenter?->name,
                'contact_person' => $venue->school?->contact_person,
                'contact_number' => $venue->school?->contact_number,
            ],
            'rooms' => fn () => $rooms->map(fn (ExamRoom $room) => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'capacity' => $room->capacity,
                'designation' => $room->designation,
                'created_at' => $room->created_at->format('M d, Y'),
            ])->values(),
            'assignments' => $assignments,
            'roomBreakdown' => $this->roomStaffing->breakdown($rooms, $assignments),
            'stats' => $this->roomStaffing->stats($rooms, $assignments),
            'designations' => fn () => self::DESIGNATIONS,
        ]);
    }

    public function store(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'designation' => ['nullable', 'string', 'max:100'],
        ]);

        $venue->rooms()->create($validated);

        return back()->with('success', "Room {$validated['room_number']} added.");
    }

    public function update(Request $request, ExamRoom $room): RedirectResponse
    {
        $room->loadMissing('examinationSchool.school');
        $this->authorizeForVenue($request->user(), $room->examinationSchool);

        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'designation' => ['nullable', 'string', 'max:100'],
        ]);

        $room->update($validated);

        return back()->with('success', 'Room updated.');
    }

    public function destroy(Request $request, ExamRoom $room): RedirectResponse
    {
        $room->loadMissing('examinationSchool.school');
        $this->authorizeForVenue($request->user(), $room->examinationSchool);

        $room->delete();

        return back()->with('success', 'Room removed.');
    }

    /** Replace all rooms for the venue with a fresh, sequentially-numbered set — matches legacy's "Generate/Regenerate Rooms". */
    public function bulkGenerate(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        DB::transaction(function () use ($venue, $validated) {
            $venue->assignments()->whereIn('role', self::ROOM_ROLES)->update(['exam_room_id' => null]);
            $venue->rooms()->delete();

            for ($n = 1; $n <= $validated['count']; $n++) {
                $venue->rooms()->create([
                    'room_number' => sprintf('Room-%03d', $n),
                    'capacity' => $validated['capacity'],
                ]);
            }
        });

        return back()->with('success', "{$validated['count']} room(s) generated.");
    }

    /** Append N more rooms without touching existing ones — matches legacy's "Add More Rooms" (additive, no confirmation). */
    public function bulkAdd(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:9999'],
            'designation' => ['nullable', 'string', 'max:100'],
        ]);

        $nextNumber = $venue->rooms()
            ->get()
            ->max(fn (ExamRoom $room) => (int) preg_replace('/\D/', '', $room->room_number)) + 1;

        for ($i = 0; $i < $validated['count']; $i++) {
            $venue->rooms()->create([
                'room_number' => sprintf('Room-%03d', $nextNumber + $i),
                'capacity' => $validated['capacity'],
                'designation' => $validated['designation'] ?? null,
            ]);
        }

        return back()->with('success', "{$validated['count']} room(s) added.");
    }

    /** Delete every room for the venue — matches legacy's "Clear All Rooms". */
    public function clearAll(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        DB::transaction(function () use ($venue) {
            $venue->assignments()->whereIn('role', self::ROOM_ROLES)->update(['exam_room_id' => null]);
            $venue->rooms()->delete();
        });

        return back()->with('success', 'All rooms cleared.');
    }

    /** Bulk-apply a designation to all rooms or only undesignated ones — matches legacy's "Override Room Designations" bulk apply. */
    public function overrideDesignation(Request $request, ExaminationSchool $venue): RedirectResponse
    {
        Gate::authorize('create', ExaminationSchool::class);
        $this->authorizeForVenue($request->user(), $venue);

        $validated = $request->validate([
            'designation' => ['required', 'string', 'max:100'],
            'scope' => ['required', 'string', 'in:all,undesignated'],
        ]);

        $venue->rooms()
            ->when($validated['scope'] === 'undesignated', fn ($q) => $q->whereNull('designation'))
            ->update(['designation' => $validated['designation']]);

        return back()->with('success', 'Room designations updated.');
    }

    private function authorizeForVenue(User $user, ExaminationSchool $venue): void
    {
        $venue->loadMissing('school');

        abort_unless(
            $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin)
                || ($user->role->isFieldOfficeScoped() && $venue->school?->handledByOffice($user->field_office_id)),
            403,
        );
    }
}
