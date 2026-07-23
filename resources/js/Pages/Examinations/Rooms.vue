<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import Tooltip from '@/Components/Tooltip.vue';

const props = defineProps({
    examination: { type: Object, required: true },
    venue: { type: Object, required: true },
    rooms: { type: Array, required: true },
    assignments: { type: Array, required: true },
    roomBreakdown: { type: Array, required: true },
    stats: { type: Object, required: true },
    designations: { type: Array, required: true },
});

/** Anchor-aware lookup — a Supervising Examiner only ever links to a group's anchor room, so non-anchor rows must read the propagated value rather than doing a naive per-room match (see RoomStaffingCalculator::breakdown()). */
const breakdownFor = (room) => props.roomBreakdown.find((b) => b.id === room.id);

const ROOM_ROLES = ['proctor', 'room_examiner', 'supervising_examiner'];
const ROOM_ROLE_LABELS = { proctor: 'Proctor', room_examiner: 'Room Examiner', supervising_examiner: 'Supervising Examiner' };
const designationOptions = computed(() => props.designations);
const DESIGNATION_HINT = 'Which exam category this room is used for. Purely descriptive — printed on room assignment reports, doesn\'t affect staffing or eligibility.';

/* ---------------------------------------------------------------------- */
/* Stats + staffing progress                                              */
/* ---------------------------------------------------------------------- */
const progressVariant = computed(() => {
    const ratio = props.stats.ratio;
    if (!props.stats.rooms_count || !ratio) return 'neutral';
    if (ratio >= 100) return 'success';
    if (ratio >= 50) return 'warning';
    return 'accent';
});
const progressBarClass = computed(() => ({
    neutral: 'bg-slate-300',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    accent: 'bg-accent-500',
}[progressVariant.value]));

/* ---------------------------------------------------------------------- */
/* Generate / Regenerate rooms                                            */
/* ---------------------------------------------------------------------- */
const showGenerate = ref(false);
const confirmingGenerate = ref(false);
const generateForm = useForm({ count: 10, capacity: 25 });
const openGenerate = () => {
    generateForm.reset();
    generateForm.clearErrors();
    showGenerate.value = true;
};
const generatePreview = computed(() => ({
    rooms: generateForm.count || 0,
    seats: (generateForm.count || 0) * (generateForm.capacity || 0),
    staffNeeded: (generateForm.count || 0) * 2 + Math.ceil((generateForm.count || 0) / 5),
}));
const submitGenerate = () => {
    if (props.rooms.length && !confirmingGenerate.value) {
        confirmingGenerate.value = true;
        return;
    }
    generateForm.post(`/venues/${props.venue.id}/rooms/generate`, {
        preserveScroll: true,
        onSuccess: () => {
            showGenerate.value = false;
            confirmingGenerate.value = false;
        },
    });
};

/* ---------------------------------------------------------------------- */
/* Add more rooms (additive — no confirmation needed, but the numbering    */
/* continues from the highest existing room number, which isn't obvious   */
/* if rooms were manually renamed/deleted, so the preview spells it out)  */
/* ---------------------------------------------------------------------- */
const showAddMore = ref(false);
const addMoreForm = useForm({ count: 5, capacity: 25, designation: '' });
const openAddMore = () => {
    addMoreForm.reset();
    addMoreForm.clearErrors();
    showAddMore.value = true;
};
/** Mirrors ExamRoomController::bulkAdd's numbering (max digits found in any existing room number, +1). */
const nextRoomNumber = computed(() => {
    if (!props.rooms.length) return 1;
    return Math.max(...props.rooms.map((r) => parseInt(r.room_number.replace(/\D/g, ''), 10) || 0)) + 1;
});
const formatRoomNumber = (n) => `Room-${String(n).padStart(3, '0')}`;
const addMorePreview = computed(() => {
    const count = addMoreForm.count || 0;
    const start = nextRoomNumber.value;
    return {
        rooms: count,
        seats: count * (addMoreForm.capacity || 0),
        rangeLabel: count > 0
            ? (count === 1 ? formatRoomNumber(start) : `${formatRoomNumber(start)} – ${formatRoomNumber(start + count - 1)}`)
            : null,
    };
});
const submitAddMore = () => addMoreForm.post(`/venues/${props.venue.id}/rooms/add-more`, {
    preserveScroll: true,
    onSuccess: () => (showAddMore.value = false),
});

/* ---------------------------------------------------------------------- */
/* Clear all rooms                                                        */
/* ---------------------------------------------------------------------- */
const confirmingClearAll = ref(false);
const clearAllForm = useForm({});
const submitClearAll = () => clearAllForm.delete(`/venues/${props.venue.id}/rooms`, {
    preserveScroll: true,
    onSuccess: () => (confirmingClearAll.value = false),
});

/* ---------------------------------------------------------------------- */
/* Edit / delete a single room                                            */
/* ---------------------------------------------------------------------- */
const editingRoom = ref(null);
const editForm = useForm({ room_number: '', capacity: 25, designation: '' });
const openEdit = (room) => {
    editingRoom.value = room;
    editForm.room_number = room.room_number;
    editForm.capacity = room.capacity;
    editForm.designation = room.designation ?? '';
    editForm.clearErrors();
};
const submitEdit = () => editForm.put(`/exam-rooms/${editingRoom.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editingRoom.value = null),
});

const removingRoom = ref(null);
const removeRoomForm = useForm({});
const confirmRemoveRoom = () => removeRoomForm.delete(`/exam-rooms/${removingRoom.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (removingRoom.value = null),
});

/* ---------------------------------------------------------------------- */
/* Designation override                                                    */
/* ---------------------------------------------------------------------- */
const showDesignationOverride = ref(false);
const bulkDesignationForm = useForm({ designation: '', scope: 'all' });
const confirmingBulkDesignation = ref(false);
const openDesignationOverride = () => {
    bulkDesignationForm.reset();
    bulkDesignationForm.clearErrors();
    showDesignationOverride.value = true;
};
const submitBulkDesignation = () => {
    if (!confirmingBulkDesignation.value) {
        confirmingBulkDesignation.value = true;
        return;
    }
    bulkDesignationForm.post(`/venues/${props.venue.id}/rooms/designations`, {
        preserveScroll: true,
        onSuccess: () => (confirmingBulkDesignation.value = false),
    });
};

const perRoomDesignations = ref({});
const initPerRoomDesignations = () => {
    perRoomDesignations.value = Object.fromEntries(props.rooms.map((r) => [r.id, r.designation ?? '']));
};
const changedPerRoomDesignations = computed(() => props.rooms.filter(
    (r) => (perRoomDesignations.value[r.id] ?? '') !== (r.designation ?? ''),
));
const perRoomForm = useForm({});
const savingPerRoom = ref(false);
const confirmingSaveAll = ref(false);
const savePerRoomDesignations = () => {
    if (!changedPerRoomDesignations.value.length) return;

    if (!confirmingSaveAll.value) {
        confirmingSaveAll.value = true;
        return;
    }

    savingPerRoom.value = true;
    let remaining = changedPerRoomDesignations.value.length;
    changedPerRoomDesignations.value.forEach((room) => {
        perRoomForm
            .transform(() => ({
                room_number: room.room_number,
                capacity: room.capacity,
                designation: perRoomDesignations.value[room.id] || null,
            }))
            .put(`/exam-rooms/${room.id}`, {
                preserveScroll: true,
                onFinish: () => {
                    remaining -= 1;
                    if (remaining <= 0) {
                        savingPerRoom.value = false;
                        confirmingSaveAll.value = false;
                    }
                },
            });
    });
};

/* ---------------------------------------------------------------------- */
/* Room Staffing Map + Manual Room Assignment                              */
/* ---------------------------------------------------------------------- */
const showStaffingMap = ref(true);

/**
 * Room/unassign edits are applied to this local copy *first* and persisted in
 * the background, so filling a venue's staffing map doesn't cost a round trip
 * (and a whole-page re-render) per dropdown. The server still has the final
 * say — roomAssignabilityRule rejects members who haven't confirmed yet and
 * rooms whose Proctor/Room Examiner slot is already taken — so every optimistic
 * edit remembers its previous value and rolls back if the PATCH fails.
 *
 * Re-seeded from props on every server response, which is what reconciles the
 * optimistic guess with the authoritative list.
 */
const localAssignments = ref([]);
watch(() => props.assignments, (incoming) => {
    localAssignments.value = incoming.map((a) => ({ ...a }));
}, { immediate: true });

const occupant = (room, role) => localAssignments.value.find((a) => a.exam_room_id === room.id && a.role === role);
const poolFor = (room, role) => localAssignments.value
    .filter((a) => a.role === role && (a.exam_room_id === null || a.exam_room_id === room.id))
    .map((a) => ({ value: a.id, label: a.member.name }));

/** Rejection messages, keyed `${roomId}:${role}` — cleared when that cell is retried. */
const cellErrors = ref({});
/** Cells with a PATCH still in flight, for a subtle pending tint (never disabled — that's the point). */
const pendingCells = ref({});

/** Optimistically move `assignment` to `roomId` (null = unassign), rolling back if the server refuses. */
const persistRoom = (assignment, roomId, cellKey) => {
    const previousRoomId = assignment.exam_room_id;

    delete cellErrors.value[cellKey];
    pendingCells.value[cellKey] = true;
    assignment.exam_room_id = roomId;

    router.patch(`/assignments/${assignment.id}/room`, { exam_room_id: roomId }, {
        preserveScroll: true,
        preserveState: true,
        // Assigning staff can't change rooms/venue/designations — skip them
        // (ExamRoomController::index leaves those props as unevaluated closures).
        only: ['assignments', 'roomBreakdown', 'stats', 'flash', 'errors'],
        onError: (errors) => {
            assignment.exam_room_id = previousRoomId;
            cellErrors.value[cellKey] = errors.exam_room_id ?? 'Could not save this assignment.';
        },
        onFinish: () => delete pendingCells.value[cellKey],
    });
};

const onCellChange = (room, role, event) => {
    const assignmentId = Number(event.target.value);
    if (!assignmentId) return;

    const assignment = localAssignments.value.find((a) => a.id === assignmentId);
    if (!assignment) return;

    persistRoom(assignment, room.id, `${room.id}:${role}`);
};

const clearCell = (assignment) => persistRoom(assignment, null, `${assignment.exam_room_id}:${assignment.role}`);

const randomizeForm = useForm({ scope: 'all' });
const confirmingRandomize = ref(null); // 'all' | 'unfilled' | null
const submitRandomize = (scope) => {
    randomizeForm.scope = scope;
    randomizeForm.post(`/venues/${props.venue.id}/staffing/randomize`, {
        preserveScroll: true,
        onSuccess: () => (confirmingRandomize.value = null),
    });
};

const confirmingClearStaffing = ref(false);
const clearStaffingForm = useForm({});
const submitClearStaffing = () => clearStaffingForm.post(`/venues/${props.venue.id}/staffing/clear`, {
    preserveScroll: true,
    onSuccess: () => (confirmingClearStaffing.value = false),
});

/* Manual Room Assignment modal — tabbed: Room Assignments (Proctor/Examiner) vs Supervising Examiners (anchor rooms only) */
const showManualAssign = ref(false);
const manualTab = ref('rooms');
const roomsPerSupervisor = 5;
const anchorRooms = computed(() => props.rooms.filter((_, index) => index % roomsPerSupervisor === 0));

const exportCsv = (rows, filename) => {
    const csv = rows.map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
};

const exportRoomAssignments = () => exportCsv(
    [['Room', 'Proctor', 'Room Examiner'], ...props.rooms.map((room) => [
        room.room_number,
        occupant(room, 'proctor')?.member.name ?? '',
        occupant(room, 'room_examiner')?.member.name ?? '',
    ])],
    `${props.venue.school_name}-room-assignments.csv`,
);

const exportSupervisingExaminers = () => exportCsv(
    [['Room Group (anchor room)', 'Supervising Examiner'], ...anchorRooms.value.map((room) => [
        room.room_number,
        occupant(room, 'supervising_examiner')?.member.name ?? '',
    ])],
    `${props.venue.school_name}-supervising-examiners.csv`,
);
</script>

<template>
    <Head :title="`Exam Rooms · ${venue.school_name}`" />

    <DashboardLayout>
        <nav class="mb-2 flex items-center gap-1.5 text-xs text-slate-400">
            <Link href="/examinations" class="hover:text-brand-700 hover:underline">Examinations</Link>
            <AppIcon name="chevron-right" class="h-3 w-3" />
            <Link :href="`/examinations/${examination.id}?step=rooms`" class="hover:text-brand-700 hover:underline">{{ examination.title }}</Link>
            <AppIcon name="chevron-right" class="h-3 w-3" />
            <span class="text-slate-600">Exam Rooms</span>
        </nav>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="font-semibold text-slate-900">{{ venue.school_name }}</p>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ examination.title }} · {{ examination.exam_date }}
                <template v-if="venue.municipality"> · {{ venue.municipality }}</template>
            </p>
            <p v-if="venue.contact_person" class="mt-0.5 text-xs text-slate-400">
                {{ venue.contact_person }}<template v-if="venue.contact_number"> · {{ venue.contact_number }}</template>
            </p>
        </div>

        <DashboardPageHeader class="mt-6" title="Exam Rooms" subtitle="Manage rooms assigned to this school for the examination.">
            <template #back>
                <BaseButton :href="`/examinations/${examination.id}?step=rooms`" variant="link">← Back to Assign Rooms</BaseButton>
            </template>
            <template #actions>
                <BaseButton v-if="rooms.length" variant="outline" size="sm" @click="openAddMore" icon="plus">
                    Add More Rooms
                </BaseButton>
                <BaseButton variant="primary" size="sm" @click="openGenerate" icon="arrow-path">
                    {{ rooms.length ? 'Regenerate Rooms' : 'Generate Rooms' }}
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total Rooms" :value="stats.rooms_count" icon="identification" />
            <StatCard label="Total Capacity" :value="stats.total_capacity" icon="users" />
            <StatCard label="Staff Required" :value="stats.required_total" icon="document-check" />
            <StatCard label="Staff Assigned" :value="`${stats.assigned_total} / ${stats.required_total}`" icon="check-circle" />
        </div>

        <div v-if="rooms.length" class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-slate-900">Staffing</p>
                <BaseBadge :variant="progressVariant">{{ stats.assigned_total }} / {{ stats.required_total }} assigned</BaseBadge>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full transition-all" :class="progressBarClass" :style="{ width: `${stats.ratio ?? 0}%` }" />
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <BaseBadge :variant="progressVariant" size="xs">{{ stats.assigned.proctor }} / {{ stats.required.proctor }} Proctor(s)</BaseBadge>
                <BaseBadge :variant="progressVariant" size="xs">{{ stats.assigned.room_examiner }} / {{ stats.required.room_examiner }} Examiner(s)</BaseBadge>
                <BaseBadge :variant="progressVariant" size="xs">{{ stats.assigned.supervising_examiner }} / {{ stats.required.supervising_examiner }} Supervisor(s)</BaseBadge>
            </div>

            <!-- Action strips -->
            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auto-Assign Staff</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <BaseButton variant="secondary" size="sm" @click="confirmingRandomize = 'all'">Randomize All</BaseButton>
                        <BaseButton variant="outline" size="sm" @click="confirmingRandomize = 'unfilled'">Fill Unfilled</BaseButton>
                        <BaseButton variant="ghost" size="sm" @click="confirmingClearStaffing = true">Clear Staffing</BaseButton>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Override Room Designations</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <BaseButton variant="outline" size="sm" @click="openDesignationOverride(); initPerRoomDesignations()">
                            Override Designations
                        </BaseButton>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Manual Room Assignment</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <BaseButton variant="outline" size="sm" @click="showManualAssign = true">
                            Assign Manually
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Room Staffing Map -->
            <div class="mt-4 border-t border-slate-100 pt-3">
                <div class="flex items-center gap-1.5">
                    <button type="button" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="showStaffingMap = !showStaffingMap">
                        <AppIcon :name="showStaffingMap ? 'chevron-down' : 'chevron-right'" class="h-3.5 w-3.5" />
                        Room Staffing Map
                    </button>
                    <Tooltip
                        wrap
                        text="Supervising Examiners oversee a group of up to 5 rooms, not one room each. Only the group's first ('anchor') room is editable for that role — every other room in the group just displays the same name, read-only."
                    >
                        <AppIcon name="information-circle" class="h-3.5 w-3.5 cursor-help text-slate-400 hover:text-slate-600" />
                    </Tooltip>
                </div>
                <div v-if="showStaffingMap" class="mt-2 overflow-x-auto">
                    <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
                        <colgroup>
                            <col class="w-1/4">
                            <col v-for="role in ROOM_ROLES" :key="role" class="w-1/4">
                        </colgroup>
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Room</th>
                                <th v-for="role in ROOM_ROLES" :key="role" class="py-2 pr-3">{{ ROOM_ROLE_LABELS[role] }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="room in rooms" :key="room.id">
                                <td class="truncate py-2 pr-3 font-medium text-slate-700">
                                    {{ room.room_number }} <span class="text-xs text-slate-400">(cap. {{ room.capacity }})</span>
                                </td>
                                <td v-for="role in ROOM_ROLES" :key="role" class="py-2 pr-3">
                                    <!-- Supervising Examiner only links to its group's anchor room — non-anchor rows show the propagated value read-only (see RoomStaffingCalculator::breakdown()). -->
                                    <template v-if="role === 'supervising_examiner' && !breakdownFor(room)?.is_supervisor_anchor">
                                        <span v-if="breakdownFor(room)?.supervising_examiner" class="min-w-0 max-w-full truncate text-xs text-slate-500" :title="`${breakdownFor(room).supervising_examiner} (via anchor room)`">
                                            {{ breakdownFor(room).supervising_examiner }}
                                        </span>
                                        <span v-else class="text-xs text-slate-300">No eligible staff</span>
                                    </template>
                                    <template v-else>
                                        <div v-if="occupant(room, role)" class="flex items-center gap-1.5" :class="{ 'opacity-60': pendingCells[`${room.id}:${role}`] }">
                                            <BaseBadge variant="success" class="min-w-0 max-w-full truncate" :title="occupant(room, role).member.name">
                                                {{ occupant(room, role).member.name }}
                                            </BaseBadge>
                                            <button type="button" class="shrink-0 text-slate-400 hover:text-accent-600" title="Unassign" @click="clearCell(occupant(room, role))">
                                                <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <select
                                            v-else-if="poolFor(room, role).length"
                                            class="w-full max-w-full rounded-lg border bg-white px-2 py-1 text-xs text-slate-600"
                                            :class="cellErrors[`${room.id}:${role}`] ? 'border-accent-400' : 'border-slate-300'"
                                            @change="onCellChange(room, role, $event)"
                                        >
                                            <option value="">— Unassigned —</option>
                                            <option v-for="option in poolFor(room, role)" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <span v-else class="text-xs text-slate-300">No eligible staff</span>
                                        <p v-if="cellErrors[`${room.id}:${role}`]" class="mt-1 text-xs leading-snug text-accent-600">
                                            {{ cellErrors[`${room.id}:${role}`] }}
                                        </p>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rooms table -->
        <div v-if="rooms.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-8 px-3 py-2">#</th>
                        <th class="px-3 py-2">Room</th>
                        <th class="px-3 py-2">Capacity</th>
                        <th class="hidden px-3 py-2 md:table-cell">
                            <span class="inline-flex items-center gap-1">
                                Designation
                                <Tooltip wrap text="Which exam category this room is used for (e.g. Professional, Sub-Professional). Purely descriptive — printed on room assignment reports, doesn't affect staffing or eligibility.">
                                    <AppIcon name="information-circle" class="h-3.5 w-3.5 cursor-help text-slate-400 hover:text-slate-600" />
                                </Tooltip>
                            </span>
                        </th>
                        <th class="hidden px-3 py-2 md:table-cell">Added On</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(room, index) in rooms" :key="room.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 text-slate-400">{{ index + 1 }}</td>
                        <td class="px-3 py-2 font-medium text-slate-900">
                            {{ room.room_number }}
                            <p v-if="room.designation" class="sm:hidden">
                                <BaseBadge variant="brand" size="xs">{{ room.designation }}</BaseBadge>
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                {{ room.capacity }} seats
                            </span>
                        </td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <BaseBadge v-if="room.designation" variant="brand" size="xs">{{ room.designation }}</BaseBadge>
                            <span v-else class="text-xs text-slate-300">—</span>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-500 md:table-cell">{{ room.created_at }}</td>
                        <td class="px-3 py-2 text-center">
                            <div class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit Room" @click="openEdit(room)" />
                                <IconButton icon="trash" label="Remove Room" variant="danger" @click="removingRoom = room" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            class="mt-6"
            icon="identification"
            title="No Rooms Yet"
            description="Click Generate Rooms above to create rooms for this venue."
        >
            <template #action>
                <BaseButton variant="primary" size="sm" @click="openGenerate" icon="arrow-path">
                    Generate Rooms
                </BaseButton>
            </template>
        </EmptyState>

        <div v-if="rooms.length" class="mt-4 flex justify-end">
            <BaseButton variant="ghost" size="sm" @click="confirmingClearAll = true" icon="trash">
                Clear All Rooms
            </BaseButton>
        </div>

        <!-- Generate / Regenerate Rooms -->
        <BaseModal :show="showGenerate" :title="rooms.length ? 'Regenerate Rooms' : 'Generate Rooms'" @close="showGenerate = false; confirmingGenerate = false">
            <div v-if="!confirmingGenerate" class="space-y-4">
                <TextInput v-model="generateForm.count" label="Number of Rooms" type="number" required :error="generateForm.errors.count" />
                <TextInput v-model="generateForm.capacity" label="Capacity per Room" type="number" required :error="generateForm.errors.capacity" />
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    {{ generatePreview.rooms }} room(s) · {{ generatePreview.seats }} total seats · ~{{ generatePreview.staffNeeded }} staff needed
                </div>
            </div>
            <div v-else class="space-y-3 text-sm leading-relaxed text-slate-600">
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                    This replaces all existing rooms for this venue. ⚠ The existing {{ rooms.length }} room(s) will be deleted, along
                    with any staffing already linked to them.
                </p>
                <p>Continue?</p>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showGenerate = false; confirmingGenerate = false">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="generateForm.processing" @click="submitGenerate">
                    {{ confirmingGenerate ? 'Yes, Replace All' : (rooms.length ? 'Regenerate' : 'Generate') }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Add More Rooms -->
        <BaseModal :show="showAddMore" title="Add More Rooms" @close="showAddMore = false">
            <div class="space-y-4">
                <TextInput v-model="addMoreForm.count" label="Number of Rooms to Add" type="number" required :error="addMoreForm.errors.count" />
                <TextInput v-model="addMoreForm.capacity" label="Capacity per Room" type="number" required :error="addMoreForm.errors.capacity" />
                <SelectInput
                    v-model="addMoreForm.designation"
                    label="Designation"
                    optional
                    placeholder="No designation"
                    :options="designationOptions"
                    :error="addMoreForm.errors.designation"
                    :hint="DESIGNATION_HINT"
                />
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    {{ addMorePreview.rooms }} room(s) · {{ addMorePreview.seats }} additional seats
                    <template v-if="addMorePreview.rangeLabel"> · numbered {{ addMorePreview.rangeLabel }}</template>
                </div>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showAddMore = false">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="addMoreForm.processing" @click="submitAddMore">Add Rooms</BaseButton>
            </template>
        </BaseModal>

        <!-- Edit Room -->
        <BaseModal :show="!!editingRoom" title="Edit Room" @close="editingRoom = null">
            <form id="edit-room-form" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <TextInput v-model="editForm.room_number" label="Room Number" required :error="editForm.errors.room_number" />
                <TextInput v-model="editForm.capacity" label="Capacity" type="number" required :error="editForm.errors.capacity" />
                <SelectInput
                    v-model="editForm.designation"
                    label="Designation"
                    optional
                    placeholder="No designation"
                    :options="designationOptions"
                    :error="editForm.errors.designation"
                    :hint="DESIGNATION_HINT"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editingRoom = null">Cancel</BaseButton>
                <BaseButton type="submit" form="edit-room-form" variant="primary" size="sm" :loading="editForm.processing">Save Changes</BaseButton>
            </template>
        </BaseModal>

        <!-- Remove room confirm -->
        <BaseModal :show="!!removingRoom" title="Remove room" @close="removingRoom = null">
            <p class="text-sm leading-relaxed text-slate-600">Remove room <strong>{{ removingRoom?.room_number }}</strong>?</p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="removingRoom = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="removeRoomForm.processing" @click="confirmRemoveRoom">Remove</BaseButton>
            </template>
        </BaseModal>

        <!-- Clear all rooms confirm -->
        <BaseModal :show="confirmingClearAll" title="Clear All Rooms?" @close="confirmingClearAll = false">
            <p class="text-sm leading-relaxed text-slate-600">
                This will remove all {{ rooms.length }} room(s) from this venue, along with any staffing linked to them.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="confirmingClearAll = false">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="clearAllForm.processing" @click="submitClearAll">Yes, clear all</BaseButton>
            </template>
        </BaseModal>

        <!-- Randomize confirm -->
        <BaseModal :show="!!confirmingRandomize" title="Randomize room staffing" @close="confirmingRandomize = null">
            <div class="space-y-3 text-sm leading-relaxed text-slate-600">
                <p v-if="confirmingRandomize === 'all'" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                    This overwrites every current room assignment for Proctor, Room Examiner, and Supervising Examiner roles at
                    <strong>{{ venue.school_name }}</strong>, then re-shuffles and reassigns them from scratch.
                </p>
                <p v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600">
                    This only fills rooms that don't have a Proctor, Room Examiner, or Supervising Examiner yet. Rooms already
                    staffed are left untouched.
                </p>
                <p>Continue?</p>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="confirmingRandomize = null">Cancel</BaseButton>
                <BaseButton variant="primary" size="sm" :loading="randomizeForm.processing" @click="submitRandomize(confirmingRandomize)">Yes, Randomize</BaseButton>
            </template>
        </BaseModal>

        <!-- Clear staffing confirm -->
        <BaseModal :show="confirmingClearStaffing" title="Clear room staffing" @close="confirmingClearStaffing = false">
            <p class="text-sm leading-relaxed text-slate-600">
                Unlink all Proctor, Room Examiner, and Supervising Examiner assignments from their rooms at
                <strong>{{ venue.school_name }}</strong>? The assignments themselves are kept — only the room links are removed.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="confirmingClearStaffing = false">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="clearStaffingForm.processing" @click="submitClearStaffing">Clear Staffing</BaseButton>
            </template>
        </BaseModal>

        <!-- Designation Override -->
        <BaseModal :show="showDesignationOverride" title="Override Room Designations" max-width="lg" @close="showDesignationOverride = false; confirmingBulkDesignation = false; confirmingSaveAll = false">
            <div class="space-y-5">
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bulk Apply</p>
                    <div v-if="!confirmingBulkDesignation" class="mt-3 flex flex-wrap items-end gap-3">
                        <SelectInput
                            v-model="bulkDesignationForm.designation"
                            label="Designation"
                            required
                            placeholder="Select a designation"
                            :options="designationOptions"
                            :error="bulkDesignationForm.errors.designation"
                        />
                        <SelectInput
                            v-model="bulkDesignationForm.scope"
                            label="Apply to"
                            :options="[{ value: 'all', label: 'All rooms' }, { value: 'undesignated', label: 'Undesignated only' }]"
                        />
                        <BaseButton variant="secondary" size="sm" :disabled="!bulkDesignationForm.designation" @click="submitBulkDesignation">
                            Apply
                        </BaseButton>
                    </div>
                    <div v-else class="mt-3 space-y-3 text-sm text-slate-600">
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                            Apply "{{ bulkDesignationForm.designation }}" to
                            {{ bulkDesignationForm.scope === 'all' ? 'all rooms' : 'undesignated rooms only' }}?
                        </p>
                        <div class="flex justify-end gap-2">
                            <BaseButton variant="outline" size="sm" @click="confirmingBulkDesignation = false">Cancel</BaseButton>
                            <BaseButton variant="primary" size="sm" :loading="bulkDesignationForm.processing" @click="submitBulkDesignation">Confirm Apply</BaseButton>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Per-Room Override</p>
                    <div class="mt-3 max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th class="py-1.5 pr-4">Room</th><th class="py-1.5">Designation</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="room in rooms" :key="room.id">
                                    <td class="py-1.5 pr-4 font-medium text-slate-700">{{ room.room_number }}</td>
                                    <td class="py-1.5">
                                        <select v-model="perRoomDesignations[room.id]" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600">
                                            <option value="">— None —</option>
                                            <option v-for="d in designationOptions" :key="d" :value="d">{{ d }}</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="confirmingSaveAll" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Update the designation for {{ changedPerRoomDesignations.length }} room(s)?
                        <button type="button" class="ml-1 font-semibold underline" @click="confirmingSaveAll = false">Cancel</button>
                    </p>
                </div>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showDesignationOverride = false">Close</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="savingPerRoom"
                    :disabled="!changedPerRoomDesignations.length"
                    @click="savePerRoomDesignations"
                >
                    {{ confirmingSaveAll ? `Yes, Save ${changedPerRoomDesignations.length}` : 'Save All' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Manual Room Assignment -->
        <BaseModal :show="showManualAssign" title="Manual Room Assignment" max-width="xl" @close="showManualAssign = false">
            <div class="space-y-4">
                <div class="flex gap-2 border-b border-slate-200">
                    <button
                        type="button"
                        class="border-b-2 px-3 py-2 text-sm font-medium"
                        :class="manualTab === 'rooms' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
                        @click="manualTab = 'rooms'"
                    >
                        Room Assignments
                    </button>
                    <button
                        type="button"
                        class="border-b-2 px-3 py-2 text-sm font-medium"
                        :class="manualTab === 'supervisors' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
                        @click="manualTab = 'supervisors'"
                    >
                        Supervising Examiners
                    </button>
                </div>

                <div v-if="manualTab === 'rooms'">
                    <div class="flex justify-end">
                        <BaseButton variant="ghost" size="sm" @click="exportRoomAssignments" icon="arrow-down-tray">
                            Export CSV
                        </BaseButton>
                    </div>
                    <div class="mt-2 max-h-80 overflow-y-auto">
                        <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
                            <colgroup>
                                <col class="w-20">
                                <col>
                                <col>
                            </colgroup>
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th class="py-1.5 pr-3">Room</th><th class="py-1.5 pr-3">Proctor</th><th class="py-1.5">Room Examiner</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="room in rooms" :key="room.id">
                                    <td class="truncate py-1.5 pr-3 font-medium text-slate-700">{{ room.room_number }}</td>
                                    <td v-for="role in ['proctor', 'room_examiner']" :key="role" class="py-1.5 pr-3">
                                        <div v-if="occupant(room, role)" class="flex items-center gap-1.5" :class="{ 'opacity-60': pendingCells[`${room.id}:${role}`] }">
                                            <BaseBadge variant="success" size="xs" class="min-w-0 max-w-full truncate" :title="occupant(room, role).member.name">
                                                {{ occupant(room, role).member.name }}
                                            </BaseBadge>
                                            <button type="button" class="shrink-0 text-slate-400 hover:text-accent-600" @click="clearCell(occupant(room, role))">
                                                <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <select
                                            v-else-if="poolFor(room, role).length"
                                            class="w-full max-w-full rounded-lg border bg-white px-2 py-1 text-xs text-slate-600"
                                            :class="cellErrors[`${room.id}:${role}`] ? 'border-accent-400' : 'border-slate-300'"
                                            @change="onCellChange(room, role, $event)"
                                        >
                                            <option value="">— Unassigned —</option>
                                            <option v-for="option in poolFor(room, role)" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <span v-else class="text-xs text-slate-300">No eligible staff</span>
                                        <p v-if="cellErrors[`${room.id}:${role}`]" class="mt-1 text-xs leading-snug text-accent-600">
                                            {{ cellErrors[`${room.id}:${role}`] }}
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else>
                    <p class="text-xs text-slate-400">
                        Each Supervising Examiner oversees a group of up to {{ roomsPerSupervisor }} rooms, anchored at the group's
                        first room.
                    </p>
                    <div class="mt-2 flex justify-end">
                        <BaseButton variant="ghost" size="sm" @click="exportSupervisingExaminers" icon="arrow-down-tray">
                            Export CSV
                        </BaseButton>
                    </div>
                    <div class="mt-2 max-h-80 overflow-y-auto">
                        <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
                            <colgroup>
                                <col class="w-40">
                                <col>
                            </colgroup>
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th class="py-1.5 pr-3">Room Group (anchor room)</th><th class="py-1.5">Supervising Examiner</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="room in anchorRooms" :key="room.id">
                                    <td class="truncate py-1.5 pr-3 font-medium text-slate-700">{{ room.room_number }}</td>
                                    <td class="py-1.5">
                                        <div v-if="occupant(room, 'supervising_examiner')" class="flex items-center gap-1.5" :class="{ 'opacity-60': pendingCells[`${room.id}:supervising_examiner`] }">
                                            <BaseBadge variant="success" size="xs" class="min-w-0 max-w-full truncate" :title="occupant(room, 'supervising_examiner').member.name">
                                                {{ occupant(room, 'supervising_examiner').member.name }}
                                            </BaseBadge>
                                            <button type="button" class="shrink-0 text-slate-400 hover:text-accent-600" @click="clearCell(occupant(room, 'supervising_examiner'))">
                                                <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <select
                                            v-else-if="poolFor(room, 'supervising_examiner').length"
                                            class="w-full max-w-full rounded-lg border bg-white px-2 py-1 text-xs text-slate-600"
                                            :class="cellErrors[`${room.id}:supervising_examiner`] ? 'border-accent-400' : 'border-slate-300'"
                                            @change="onCellChange(room, 'supervising_examiner', $event)"
                                        >
                                            <option value="">— Unassigned —</option>
                                            <option v-for="option in poolFor(room, 'supervising_examiner')" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <span v-else class="text-xs text-slate-300">No eligible staff</span>
                                        <p v-if="cellErrors[`${room.id}:supervising_examiner`]" class="mt-1 text-xs leading-snug text-accent-600">
                                            {{ cellErrors[`${room.id}:supervising_examiner`] }}
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showManualAssign = false">Done</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
