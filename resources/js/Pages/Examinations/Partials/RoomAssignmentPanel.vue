<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseTable from '@/Components/BaseTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Tooltip from '@/Components/Tooltip.vue';

const props = defineProps({
    examination: { type: Object, required: true },
    venues: { type: Array, required: true },
    can: { type: Object, required: true },
});

/** A Supervising Examiner covers a group of rooms anchored at the first room of the group (see StaffingRandomizer) — only the anchor row is editable. */
const ROOM_ROLE_FIELDS = [
    { key: 'proctor', label: 'Proctor' },
    { key: 'room_examiner', label: 'Room Examiner' },
    { key: 'supervising_examiner', label: 'Supervising Examiner' },
];

const venuesWithRooms = computed(() => props.venues.filter((v) => v.rooms.length));

const venueFilter = ref('');
const statusFilter = ref('all');

const venueOptions = computed(() => [
    { value: '', label: 'All Venues' },
    ...venuesWithRooms.value.map((v) => ({ value: String(v.id), label: v.school_name })),
]);

const statusOptions = [
    { value: 'all', label: 'All Rooms' },
    { value: 'complete', label: 'Complete Only' },
    { value: 'incomplete', label: 'Incomplete Only' },
];

const filteredVenues = computed(() => venuesWithRooms.value.filter(
    (v) => !venueFilter.value || String(v.id) === venueFilter.value,
));

const roomsMatchingStatus = (venue) => venue.room_breakdown.filter((room) => (
    statusFilter.value === 'all' || (statusFilter.value === 'complete' ? room.complete : !room.complete)
));

const overallStats = computed(() => {
    const rooms = filteredVenues.value.flatMap((v) => v.room_breakdown);
    const complete = rooms.filter((r) => r.complete).length;
    return { total: rooms.length, complete, incomplete: rooms.length - complete };
});

const progressVariant = (venue) => {
    const ratio = venue.staffing.ratio;
    if (!venue.rooms_count || !ratio) return 'neutral';
    if (ratio >= 100) return 'success';
    if (ratio >= 50) return 'warning';
    return 'accent';
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (venueFilter.value) params.set('venue_id', venueFilter.value);
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value);
    const query = params.toString();
    return `/examinations/${props.examination.id}/room-assignments/export${query ? `?${query}` : ''}`;
});

/* --- Inline assign / unassign directly from the breakdown table --- */
const canEditField = (room, fieldKey) => fieldKey !== 'supervising_examiner' || room.is_supervisor_anchor;

const assignRoomForm = useForm({ exam_room_id: null });
const savingCell = ref(null);

const assignToRoom = (room, fieldKey, assignmentId) => {
    if (!assignmentId) return;
    savingCell.value = `${room.id}:${fieldKey}`;
    assignRoomForm
        .transform(() => ({ exam_room_id: room.id }))
        .patch(`/assignments/${assignmentId}/room`, { preserveScroll: true, onFinish: () => (savingCell.value = null) });
};

const clearRoom = (room, fieldKey) => {
    const assignmentId = room[`${fieldKey}_assignment_id`];
    if (!assignmentId) return;
    savingCell.value = `${room.id}:${fieldKey}`;
    assignRoomForm
        .transform(() => ({ exam_room_id: null }))
        .patch(`/assignments/${assignmentId}/room`, { preserveScroll: true, onFinish: () => (savingCell.value = null) });
};
</script>

<template>
    <div class="space-y-6">
        <BaseCard padding="none" class="p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Step 3 · Assign Rooms</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Assign an unassigned Proctor, Room Examiner, or Supervising Examiner directly from the breakdown
                        below, or open a school's Exam Rooms workspace to generate rooms, override designations, or
                        auto-assign staffing in bulk.
                    </p>
                </div>
                <BaseButton v-if="venuesWithRooms.length" :href="exportUrl" external variant="secondary" size="sm" icon="arrow-down-tray">
                    Export to Excel
                </BaseButton>
            </div>
        </BaseCard>

        <template v-if="venuesWithRooms.length">
            <BaseCard padding="none" class="p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                    <SelectInput v-model="venueFilter" label="Venue" placeholder="All Venues" :options="venueOptions" />
                    <SelectInput v-model="statusFilter" label="Room Status" placeholder="All Rooms" :options="statusOptions" />
                    <div class="flex items-end gap-2 pb-0.5">
                        <div class="min-w-[4.5rem] rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center">
                            <p class="text-lg font-bold text-emerald-700">{{ overallStats.complete }}</p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-emerald-600">Complete</p>
                        </div>
                        <div class="min-w-[4.5rem] rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center">
                            <p class="text-lg font-bold text-amber-700">{{ overallStats.incomplete }}</p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-amber-600">Incomplete</p>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <div v-for="venue in filteredVenues" :key="venue.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ venue.school_name }}</p>
                        <p class="text-xs text-slate-500">{{ venue.rooms_count }} room(s)<template v-if="venue.municipality"> · {{ venue.municipality }}</template></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <BaseBadge :variant="progressVariant(venue)" size="xs">
                            {{ venue.staffing.assigned_total }} / {{ venue.staffing.required_total }} staffed
                        </BaseBadge>
                        <BaseButton :href="`/venues/${venue.id}/rooms`" variant="outline" size="sm">
                            Manage Rooms
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </BaseButton>
                    </div>
                </div>

                <BaseTable v-if="roomsMatchingStatus(venue).length" bare>
                    <template #head>
                            <tr>
                                <th class="px-3 py-2">Room</th>
                                <th class="hidden px-3 py-2 sm:table-cell">Designation</th>
                                <th v-for="field in ROOM_ROLE_FIELDS" :key="field.key" class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1">
                                        {{ field.label }}
                                        <Tooltip
                                            v-if="field.key === 'supervising_examiner'"
                                            wrap
                                            text="A Supervising Examiner oversees a group of up to 5 rooms. Only the group's first ('anchor') room is editable here — the others just display the same name, read-only."
                                        >
                                            <AppIcon name="information-circle" class="h-3.5 w-3.5 cursor-help text-slate-400 hover:text-slate-600" />
                                        </Tooltip>
                                    </span>
                                </th>
                                <th class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center gap-1">
                                        Status
                                        <Tooltip wrap text="Complete means this room has all three roles filled: Proctor, Room Examiner, and its group's Supervising Examiner.">
                                            <AppIcon name="information-circle" class="h-3.5 w-3.5 cursor-help text-slate-400 hover:text-slate-600" />
                                        </Tooltip>
                                    </span>
                                </th>
                            </tr>
                    </template>

                            <tr
                                v-for="room in roomsMatchingStatus(venue)"
                                :key="room.id"
                                class="transition-colors hover:bg-brand-50/40"
                                :class="!room.complete && 'bg-amber-50/30'"
                            >
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">{{ room.room_number }}</td>
                                <td class="hidden px-3 py-2 text-slate-500 sm:table-cell">{{ room.designation ?? '—' }}</td>

                                <td v-for="field in ROOM_ROLE_FIELDS" :key="field.key" class="max-w-[10rem] px-3 py-2">
                                    <div v-if="room[field.key]" class="flex items-center gap-1.5">
                                        <span class="truncate text-slate-700" :title="room[field.key]">{{ room[field.key] }}</span>
                                        <button
                                            v-if="can.assign && canEditField(room, field.key)"
                                            type="button"
                                            class="-my-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-accent-50 hover:text-accent-600 disabled:pointer-events-none disabled:opacity-40"
                                            :disabled="savingCell === `${room.id}:${field.key}`"
                                            :aria-label="`Unassign ${room[field.key]} as ${field.label}`"
                                            @click="clearRoom(room, field.key)"
                                        >
                                            <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <select
                                        v-else-if="can.assign && canEditField(room, field.key) && venue.unassigned_pool[field.key]?.length"
                                        class="w-full max-w-[9.5rem] rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-200 disabled:opacity-50"
                                        :disabled="savingCell === `${room.id}:${field.key}`"
                                        @change="assignToRoom(room, field.key, $event.target.value); $event.target.value = ''"
                                    >
                                        <option value="" disabled selected>Assign…</option>
                                        <option v-for="opt in venue.unassigned_pool[field.key]" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                    </select>
                                    <span v-else class="font-medium text-amber-600">Unassigned</span>
                                </td>

                                <td class="px-3 py-2 text-center">
                                    <BaseBadge :variant="room.complete ? 'success' : 'warning'" size="xs">
                                        {{ room.complete ? 'Complete' : 'Incomplete' }}
                                    </BaseBadge>
                                </td>
                            </tr>
                </BaseTable>
                <p v-else class="px-4 py-6 text-center text-sm text-slate-400">
                    No rooms match the current status filter.
                </p>
            </div>
        </template>

        <EmptyState
            v-else
            icon="building-office"
            title="No rooms to staff yet"
            description="Go back to Step 1 and generate rooms for at least one school before assigning room staffing."
        />
    </div>
</template>
