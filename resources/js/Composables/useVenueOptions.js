import { computed, unref } from 'vue';

/**
 * Shared venue/room `<select>` option derivation for Examinations/Show.vue's
 * assign-member form and its edit/force-reassign modals — all three need the
 * same "venue -> its rooms" lookup against the same `venues` prop.
 */
export function useVenueOptions(venues) {
    const venueOptions = computed(() => unref(venues).map((v) => ({ value: v.id, label: v.school_name })));

    const roomOptionsFor = (venueId) => {
        const venue = unref(venues).find((v) => v.id === Number(venueId));
        return venue ? venue.rooms.map((r) => ({ value: r.id, label: `${r.room_number} (cap. ${r.capacity})` })) : [];
    };

    return { venueOptions, roomOptionsFor };
}
