import { computed, unref } from 'vue';

// Exactly one Proctor and one Room Examiner per room — matches
// ExamAssignmentController::ONE_PER_ROOM_ROLES. Supervising Examiner is
// anchor-group based (see RoomStaffingCalculator), not a simple per-room
// slot, so it's intentionally left out of this filtering.
const ONE_PER_ROOM_ROLES = ['proctor', 'room_examiner'];

/**
 * Shared venue/room `<select>` option derivation for Examinations/Show.vue's
 * assign-member form and its edit/force-reassign modals — all three need the
 * same "venue -> its rooms" lookup against the same `venues` prop.
 */
export function useVenueOptions(venues) {
    const venueOptions = computed(() => unref(venues).map((v) => ({ value: v.id, label: v.school_name })));

    /**
     * Room choices for a venue. For Proctor/Room Examiner, rooms whose slot
     * is already staffed are left out of the list entirely so they can't be
     * picked twice — `excludeAssignmentId` keeps an assignment's OWN current
     * room selectable while editing it (it would otherwise look "taken" by
     * itself). Other roles (or no role yet) get the full, unfiltered list.
     */
    const roomOptionsFor = (venueId, role = null, excludeAssignmentId = null) => {
        const venue = unref(venues).find((v) => v.id === Number(venueId));
        if (!venue) return [];

        if (!role || !ONE_PER_ROOM_ROLES.includes(role)) {
            return venue.rooms.map((r) => ({ value: r.id, label: `${r.room_number} (cap. ${r.capacity})` }));
        }

        return venue.room_breakdown
            .filter((room) => !room[role] || room[`${role}_assignment_id`] === excludeAssignmentId)
            .map((room) => ({ value: room.id, label: `${room.room_number} (cap. ${room.capacity})` }));
    };

    /** How many of the venue's rooms already have this role filled — powers an empty-list explainer. */
    const staffedRoomCountFor = (venueId, role) => {
        const venue = unref(venues).find((v) => v.id === Number(venueId));
        if (!venue || !ONE_PER_ROOM_ROLES.includes(role)) return 0;
        return venue.room_breakdown.filter((room) => room[role]).length;
    };

    return { venueOptions, roomOptionsFor, staffedRoomCountFor, ONE_PER_ROOM_ROLES };
}
