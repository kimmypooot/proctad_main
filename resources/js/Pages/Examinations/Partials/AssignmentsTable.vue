<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useVenueOptions } from '@/Composables/useVenueOptions';

const props = defineProps({
    examination: { type: Object, required: true },
    assignments: { type: Array, required: true },
    venues: { type: Array, required: true },
    roles: { type: Array, required: true },
    ratings: { type: Array, required: true },
    can: { type: Object, required: true },
});

const { venueOptions, roomOptionsFor, staffedRoomCountFor, ONE_PER_ROOM_ROLES } = useVenueOptions(computed(() => props.venues));

/* --- Status quick-filter (All / Pending / Confirmed / Declined / Expired / Cancelled) --- */
const statusFilter = ref('all');

const statusCounts = computed(() => {
    const counts = { all: props.assignments.length };
    for (const assignment of props.assignments) {
        counts[assignment.status] = (counts[assignment.status] ?? 0) + 1;
    }
    return counts;
});

const statusFilterOptions = computed(() => [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'declined', label: 'Declined' },
    { value: 'expired', label: 'Expired' },
    { value: 'cancelled', label: 'Cancelled' },
].filter((option) => option.value === 'all' || statusCounts.value[option.value] > 0));

const filteredAssignments = computed(() => (
    statusFilter.value === 'all'
        ? props.assignments
        : props.assignments.filter((a) => a.status === statusFilter.value)
));

/* --- Grouped by committee (REC / LEC / Special / School) --- */
const groupByCommittee = ref(true);

const groupedAssignments = computed(() => {
    if (!groupByCommittee.value) {
        return [{ key: 'all', label: null, rows: filteredAssignments.value }];
    }

    const order = [];
    const byGroup = new Map();

    for (const assignment of filteredAssignments.value) {
        if (!byGroup.has(assignment.role_group)) {
            byGroup.set(assignment.role_group, { key: assignment.role_group, label: assignment.role_group_label, rows: [] });
            order.push(assignment.role_group);
        }
        byGroup.get(assignment.role_group).rows.push(assignment);
    }

    return order.map((key) => byGroup.get(key));
});

/* --- Edit assignment --- */
const editingAssignment = ref(null);
const editForm = useForm({
    role: '', performance_rating: '', remarks: '', attended: false,
    examination_school_id: '', exam_room_id: '', covered_school_ids: [],
});

const editIsCoverageRole = computed(() => props.roles.find((r) => r.value === editForm.role)?.is_coverage ?? false);
const overrideComputedRating = ref(false);

// REC monitors region-wide; LEC is seated at one testing center and only ever
// covers schools inside it. Mirrors coveredSchoolJurisdictionRule server-side —
// offering the full region here would just produce a validation error later.
const editIsCenterBoundCoverage = computed(
    () => props.roles.find((r) => r.value === editForm.role)?.group === 'testing_center',
);

const editCoveredSchoolOptions = computed(() => {
    if (!editIsCenterBoundCoverage.value) {
        return venueOptions.value;
    }

    const center = props.venues.find((v) => v.id === Number(editForm.examination_school_id))?.field_office_id;
    if (!center) return [];

    const inCenter = props.venues.filter((v) => v.field_office_id === center).map((v) => v.id);

    return venueOptions.value.filter((option) => inCenter.includes(option.value));
});

// Changing the role or venue can strip options out from under an existing
// selection — drop the now-ineligible ones rather than submitting them.
watch(editCoveredSchoolOptions, (options) => {
    const allowed = options.map((option) => option.value);
    editForm.covered_school_ids = editForm.covered_school_ids.filter((id) => allowed.includes(id));
});

const openEdit = (assignment) => {
    editingAssignment.value = assignment;
    editForm.clearErrors();
    overrideComputedRating.value = false;
    editForm.role = assignment.role;
    // Prefill from the raw manual column, not `rating` (which may reflect a
    // computed value) — otherwise saving the form would bake the computed
    // rating into performance_rating even if the staff never touched it.
    editForm.performance_rating = assignment.manual_rating ?? '';
    editForm.remarks = assignment.remarks ?? '';
    editForm.attended = assignment.attended;
    editForm.examination_school_id = assignment.examination_school_id ?? '';
    editForm.exam_room_id = assignment.exam_room_id ?? '';
    editForm.covered_school_ids = assignment.covered_schools.map((s) => s.id);
};

const toggleEditCoveredSchool = (id) => {
    const index = editForm.covered_school_ids.indexOf(id);
    if (index === -1) {
        editForm.covered_school_ids.push(id);
    } else {
        editForm.covered_school_ids.splice(index, 1);
    }
};

const editIsRoomExclusiveRole = computed(() => ONE_PER_ROOM_ROLES.includes(editForm.role));
const editRoleLabel = computed(() => props.roles.find((r) => r.value === editForm.role)?.label ?? '');
const editRoomOptions = computed(() => roomOptionsFor(editForm.examination_school_id, editForm.role, editingAssignment.value?.id));
const editStaffedRoomCount = computed(() => staffedRoomCountFor(editForm.examination_school_id, editForm.role));

// A role change may invalidate the previously-picked room (now staffed by
// someone else for the new role) — drop it instead of silently resubmitting
// a room the server will reject anyway.
watch(editRoomOptions, (options) => {
    if (editForm.exam_room_id && !options.some((o) => o.value === editForm.exam_room_id)) {
        editForm.exam_room_id = '';
    }
});

const saveEdit = () => editForm
    .transform((data) => ({
        ...data,
        performance_rating: data.performance_rating || null,
        examination_school_id: data.examination_school_id || null,
        exam_room_id: data.exam_room_id || null,
        covered_school_ids: editIsCoverageRole.value ? data.covered_school_ids : [],
    }))
    .put(`/assignments/${editingAssignment.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (editingAssignment.value = null),
    });

/* --- Remove assignment --- */
const removing = ref(null);
const removeForm = useForm({});
const confirmRemove = () => removeForm.delete(`/assignments/${removing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (removing.value = null),
});

/* --- Send confirmation --- */
const sendConfirmationForm = useForm({});
const sendConfirmation = (assignment) => sendConfirmationForm.post(`/assignments/${assignment.id}/send-confirmation`, {
    preserveScroll: true,
});

/*
 * --- Exam-day cover ---
 *
 * A test administrator who does not report is marked absent, then an Alternate
 * Examiner from this venue's standby pool takes the seat, inheriting its
 * designation and room. The server owns every rule here (AlternateActivator);
 * these handlers only choose what to offer.
 */
const markingAbsent = ref(null);
const absenceForm = useForm({});

const confirmMarkAbsent = () => absenceForm.post(`/assignments/${markingAbsent.value.id}/absent`, {
    preserveScroll: true,
    onSuccess: () => (markingAbsent.value = null),
});

const clearAbsence = (assignment) => absenceForm.delete(`/assignments/${assignment.id}/absent`, {
    preserveScroll: true,
});

const standDown = (assignment) => absenceForm.delete(`/assignments/${assignment.id}/alternate`, {
    preserveScroll: true,
});

const callingIn = ref(null);
const alternateForm = useForm({ alternate_assignment_id: '' });

// The standby pool is per venue, so only alternates at the vacant seat's own
// venue are offered — matching AlternateActivator::cannotActivate, which would
// refuse anyone else.
const availableAlternates = computed(() => (callingIn.value === null ? [] : props.assignments.filter(
    (a) => a.is_alternate
        && !a.covering_for
        && !a.absent
        && a.examination_school_id === callingIn.value.examination_school_id,
)));

const openAlternates = (assignment) => {
    callingIn.value = assignment;
    alternateForm.clearErrors();
    alternateForm.alternate_assignment_id = '';
};

const submitAlternate = () => alternateForm.post(`/assignments/${callingIn.value.id}/alternate`, {
    preserveScroll: true,
    onSuccess: () => (callingIn.value = null),
});

/* --- Force reassign (admin override — preserves confirmation status) --- */
const reassigning = ref(null);
const reassignForm = useForm({ role: '', examination_school_id: '' });

const openReassign = (assignment) => {
    reassigning.value = assignment;
    reassignForm.clearErrors();
    reassignForm.role = assignment.role;
    reassignForm.examination_school_id = assignment.examination_school_id ?? '';
};

const submitReassign = () => reassignForm.post(`/assignments/${reassigning.value.id}/force-reassign`, {
    preserveScroll: true,
    onSuccess: () => (reassigning.value = null),
});

/* --- Bulk select: Manual Confirm (any assign-capable role) + Revoke (super admin only) --- */
const selectedForRevoke = ref([]);
const revoking = ref(false);
const revokeForm = useForm({ assignment_ids: [] });

const submitBulkRevoke = () => {
    revokeForm.assignment_ids = selectedForRevoke.value;
    revokeForm.post('/assignments/bulk-revoke', {
        preserveScroll: true,
        onSuccess: () => {
            selectedForRevoke.value = [];
            revoking.value = false;
        },
    });
};

const selectedPendingCount = computed(() => props.assignments
    .filter((a) => selectedForRevoke.value.includes(a.id) && a.status === 'pending')
    .length);

const confirmForm = useForm({ assignment_ids: [] });
const submitBulkConfirm = () => {
    confirmForm.assignment_ids = props.assignments
        .filter((a) => selectedForRevoke.value.includes(a.id) && a.status === 'pending')
        .map((a) => a.id);
    confirmForm.post('/assignments/bulk-confirm', {
        preserveScroll: true,
        onSuccess: () => (selectedForRevoke.value = []),
    });
};
</script>

<template>
    <div v-if="(can.assign || can.bulkRevoke) && selectedForRevoke.length" class="mt-6 flex items-center justify-between rounded-lg border border-accent-200 bg-accent-50 px-4 py-3">
        <p class="text-sm font-medium text-accent-800">{{ selectedForRevoke.length }} assignment(s) selected</p>
        <div class="flex gap-2">
            <BaseButton v-if="can.assign && selectedPendingCount > 0" variant="secondary" size="sm" :loading="confirmForm.processing" @click="submitBulkConfirm">
                Confirm Selected ({{ selectedPendingCount }})
            </BaseButton>
            <BaseButton v-if="can.bulkRevoke" variant="accent" size="sm" @click="revoking = true">Revoke Selected</BaseButton>
        </div>
    </div>

    <div v-if="assignments.length" class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in statusFilterOptions"
                :key="option.value"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="statusFilter === option.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="statusFilter = option.value"
            >
                {{ option.label }}
                <span v-if="option.value !== 'all'">({{ statusCounts[option.value] ?? 0 }})</span>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Group by committee</span>
            <button
                type="button"
                role="switch"
                :aria-checked="groupByCommittee"
                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                :class="groupByCommittee ? 'bg-brand-600' : 'bg-slate-300'"
                @click="groupByCommittee = !groupByCommittee"
            >
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform" :class="groupByCommittee ? 'translate-x-4.5' : 'translate-x-1'" />
            </button>
        </div>
    </div>

    <div v-if="filteredAssignments.length" class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th v-if="can.assign || can.bulkRevoke" class="w-8 px-3 py-2"><span class="sr-only">Select</span></th>
                    <th class="px-3 py-2">Member</th>
                    <th class="hidden px-3 py-2 xl:table-cell">Testing Center</th>
                    <th class="px-3 py-2">Role</th>
                    <th class="hidden px-3 py-2 md:table-cell">Venue / Room</th>
                    <th class="px-3 py-2">Confirmation</th>
                    <th class="hidden px-3 py-2 md:table-cell">Attendance</th>
                    <th class="hidden px-3 py-2 xl:table-cell">Rating</th>
                    <th class="px-3 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template v-for="group in groupedAssignments" :key="group.key">
                    <tr v-if="group.label" class="bg-slate-50">
                        <td :colspan="(can.assign || can.bulkRevoke) ? 9 : 8" class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ group.label }} <span class="font-normal normal-case text-slate-400">({{ group.rows.length }})</span>
                        </td>
                    </tr>
                    <tr v-for="assignment in group.rows" :key="assignment.id" class="transition-colors hover:bg-brand-50/40">
                    <td v-if="can.assign || can.bulkRevoke" class="px-3 py-2">
                        <input
                            v-model="selectedForRevoke"
                            type="checkbox"
                            :value="assignment.id"
                            class="h-4 w-4 rounded border-slate-300 text-accent-600 accent-accent-600"
                        >
                    </td>
                    <td class="max-w-[10rem] px-3 py-2 sm:max-w-[12rem]">
                        <Link href="/members" class="font-medium text-slate-900 hover:underline">
                            {{ assignment.member.name }}
                        </Link>
                        <p class="font-mono text-xs text-brand-700">{{ assignment.member.proctad_id }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-400 xl:hidden">{{ assignment.field_office?.name }}</p>
                    </td>
                    <td class="hidden max-w-[8rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="assignment.field_office?.name">{{ assignment.field_office?.name }}</td>
                    <td class="max-w-[7rem] px-3 py-2 text-slate-600">
                        {{ assignment.role_label }}
                        <!--
                            Both sides of a substitution are stated on the row.
                            Without this the alternate simply reads as a Proctor
                            and the no-show as a Proctor who never attended,
                            with nothing connecting them.
                        -->
                        <span v-if="assignment.covering_for" class="mt-0.5 block text-xs text-brand-700">
                            covering for {{ assignment.covering_for.member_name }}
                        </span>
                        <span v-else-if="assignment.covered_by" class="mt-0.5 block text-xs text-slate-400">
                            covered by {{ assignment.covered_by.member_name }}
                        </span>
                    </td>
                    <td class="hidden max-w-[9rem] px-3 py-2 text-slate-600 md:table-cell">
                        <template v-if="assignment.venue">
                            <span class="line-clamp-2">{{ assignment.venue }}<span v-if="assignment.room"> — {{ assignment.room }}</span></span>
                        </template>
                        <span v-else class="text-slate-400">—</span>
                        <div v-if="assignment.covered_schools.length" class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="school in assignment.covered_schools"
                                :key="school.id"
                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[0.65rem] font-medium text-slate-600"
                                :title="school.attended ? 'Attendance recorded' : 'Not yet scanned'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="school.attended ? 'bg-emerald-500' : 'bg-slate-300'" />
                                {{ school.name }}
                            </span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <BaseBadge :variant="assignment.status_variant">{{ assignment.status_label }}</BaseBadge>
                    </td>
                    <td class="hidden whitespace-nowrap px-3 py-2 md:table-cell">
                        <span v-if="assignment.attended" class="inline-flex items-center gap-1.5 text-emerald-700">
                            <AppIcon name="check-circle" class="h-4 w-4" />
                            <span class="text-xs">{{ assignment.attendance_confirmed_at }}</span>
                        </span>
                        <!--
                            Absent is its own state, not merely "no timestamp":
                            everyone not yet scanned also has no timestamp, and
                            the difference is what justifies calling an
                            alternate in.
                        -->
                        <span v-else-if="assignment.absent" class="inline-flex items-center gap-1.5 text-accent-700">
                            <AppIcon name="x-mark" class="h-4 w-4" />
                            <span class="text-xs">Absent · {{ assignment.marked_absent_at }}</span>
                        </span>
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="hidden px-3 py-2 xl:table-cell">
                        <BaseBadge v-if="assignment.rating_label" :variant="assignment.rating_variant">
                            {{ assignment.rating_label }}
                            <AppIcon
                                v-if="assignment.rating_is_computed"
                                name="sparkles"
                                class="ml-1 inline h-3 w-3"
                                title="Computed from evaluations"
                            />
                        </BaseBadge>
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div v-if="assignment.can_manage" class="inline-flex gap-1">
                            <IconButton
                                v-if="assignment.status === 'pending' || assignment.status === 'declined' || assignment.status === 'expired'"
                                icon="paper-airplane"
                                :label="`${assignment.confirmation_sent_at ? 'Resend' : 'Send'} Confirmation`"
                                @click="sendConfirmation(assignment)"
                            />
                            <!--
                                Exam-day cover. Only offered where it can
                                actually apply: a seat that is not an alternate
                                and whose holder has not reported in.
                            -->
                            <IconButton
                                v-if="!assignment.attended && !assignment.absent && !assignment.is_alternate && !assignment.covering_for"
                                icon="x-mark"
                                label="Mark absent"
                                @click="markingAbsent = assignment"
                            />
                            <IconButton
                                v-if="assignment.absent && !assignment.covered_by"
                                icon="user-plus"
                                label="Call in an alternate"
                                @click="openAlternates(assignment)"
                            />
                            <IconButton
                                v-if="assignment.absent && !assignment.covered_by"
                                icon="arrow-path"
                                label="Clear absence"
                                @click="clearAbsence(assignment)"
                            />
                            <IconButton
                                v-if="assignment.covering_for"
                                icon="arrow-path"
                                label="Stand down (return to standby pool)"
                                @click="standDown(assignment)"
                            />
                            <IconButton icon="pencil" label="Edit" @click="openEdit(assignment)" />
                            <IconButton
                                v-if="venues.length"
                                icon="arrow-path"
                                label="Force Reassign"
                                @click="openReassign(assignment)"
                            />
                            <IconButton icon="trash" label="Remove" variant="danger" @click="removing = assignment" />
                        </div>
                    </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div v-else-if="assignments.length" class="mt-6">
        <EmptyState
            icon="users"
            title="No assignments match this filter"
            description="Try a different status filter above."
        />
    </div>

    <div v-else class="mt-6">
        <EmptyState
            icon="users"
            title="No assignments yet"
            description="Assign PROCTAD members to this examination to build their service records."
        />
    </div>

    <!-- Edit assignment modal -->
    <BaseModal :show="!!editingAssignment" title="Edit Service Record" @close="editingAssignment = null">
        <form id="assignment-form" class="space-y-4" novalidate @submit.prevent="saveEdit">
            <p class="text-sm text-slate-600">
                <strong>{{ editingAssignment?.member.name }}</strong>
                <span class="ml-1 font-mono text-xs text-brand-700">{{ editingAssignment?.member.proctad_id }}</span>
            </p>
            <SelectInput v-model="editForm.role" label="Role Performed" required :options="roles" :error="editForm.errors.role" />
            <SelectInput
                v-model="editForm.examination_school_id"
                label="Venue"
                optional
                placeholder="Not yet assigned"
                :options="venueOptions"
                :error="editForm.errors.examination_school_id"
                @update:model-value="editForm.exam_room_id = ''"
            />
            <SelectInput
                v-model="editForm.exam_room_id"
                label="Room"
                optional
                :placeholder="editIsRoomExclusiveRole && editForm.examination_school_id && !editRoomOptions.length
                    ? 'No open rooms for this role'
                    : 'Not yet assigned'"
                :options="editRoomOptions"
                :error="editForm.errors.exam_room_id"
            />
            <p
                v-if="editIsRoomExclusiveRole && editForm.examination_school_id && !editRoomOptions.length"
                class="text-xs text-amber-700"
            >
                Every room at this venue already has a {{ editRoleLabel }} assigned ({{ editStaffedRoomCount }} room(s)).
            </p>
            <div
                v-if="editingAssignment?.rating_is_computed"
                class="rounded-lg border border-brand-200 bg-brand-50/60 p-3 text-sm text-brand-800"
            >
                <p class="font-medium">Rating computed automatically</p>
                <p class="mt-0.5 text-xs">
                    Based on {{ editingAssignment.rating_computed_count }} evaluation(s) from the Supervising Examiner
                    (average {{ editingAssignment.rating_computed_average }}/5).
                </p>
                <CheckboxInput v-model="overrideComputedRating" class="mt-2">Override with a manual rating</CheckboxInput>
            </div>
            <SelectInput
                v-if="!editingAssignment?.rating_is_computed || overrideComputedRating"
                v-model="editForm.performance_rating"
                label="Performance Rating"
                optional
                placeholder="Not yet rated"
                :options="[{ value: '', label: 'Not yet rated' }, ...ratings]"
                :error="editForm.errors.performance_rating"
            />
            <TextInput v-model="editForm.remarks" label="Remarks" optional :error="editForm.errors.remarks" />
            <CheckboxInput v-model="editForm.attended">Attendance confirmed</CheckboxInput>
            <div v-if="editIsCoverageRole" class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-sm font-medium text-slate-700">Covered schools</p>
                <p class="mt-0.5 text-xs text-slate-500">Reference-only — no confirmation sent; scanned/entered per school on exam day.</p>
                <p v-if="editIsCenterBoundCoverage" class="mt-0.5 text-xs text-slate-500">
                    Local Examination Committee roles cover only schools within their own testing center.
                </p>
                <div v-if="editCoveredSchoolOptions.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1.5">
                    <label
                        v-for="venue in editCoveredSchoolOptions"
                        :key="venue.value"
                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            type="checkbox"
                            :checked="editForm.covered_school_ids.includes(venue.value)"
                            class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                            @change="toggleEditCoveredSchool(venue.value)"
                        >
                        {{ venue.label }}
                    </label>
                </div>
                <p v-else-if="editIsCenterBoundCoverage && !editForm.examination_school_id" class="mt-2 text-xs text-slate-400">
                    Set this assignment's venue first — it decides which testing center's schools can be covered.
                </p>
                <p v-else class="mt-2 text-xs text-slate-400">No venues attached yet.</p>
            </div>
        </form>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="editingAssignment = null">Cancel</BaseButton>
            <BaseButton
                type="submit"
                form="assignment-form"
                variant="primary"
                size="sm"
                :loading="editForm.processing"
                :disabled="editForm.processing"
            >
                Save
            </BaseButton>
        </template>
    </BaseModal>

    <!-- Remove assignment confirm -->
    <BaseModal :show="!!removing" title="Remove assignment" @close="removing = null">
        <p class="text-sm leading-relaxed text-slate-600">
            Remove <strong>{{ removing?.member.name }}</strong> from {{ examination.title }}?
            This deletes the service record for this examination.
        </p>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="removing = null">Cancel</BaseButton>
            <BaseButton variant="accent" size="sm" :loading="removeForm.processing" @click="confirmRemove">
                Remove
            </BaseButton>
        </template>
    </BaseModal>

    <!-- Mark absent -->
    <BaseModal :show="!!markingAbsent" title="Mark absent" @close="markingAbsent = null">
        <p class="text-sm leading-relaxed text-slate-600">
            Record that <strong>{{ markingAbsent?.member.name }}</strong> did not report as
            {{ markingAbsent?.role_label }}<span v-if="markingAbsent?.room"> in Room {{ markingAbsent.room }}</span>?
        </p>
        <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
            This frees the seat so an Alternate Examiner can be called in. It is not the same as
            &ldquo;not yet scanned&rdquo; — use it only once you are satisfied they are not coming.
            You can clear it again until an alternate has taken the seat.
        </p>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="markingAbsent = null">Cancel</BaseButton>
            <BaseButton variant="accent" size="sm" :loading="absenceForm.processing" @click="confirmMarkAbsent">
                Mark absent
            </BaseButton>
        </template>
    </BaseModal>

    <!-- Call in an alternate -->
    <BaseModal :show="!!callingIn" title="Call in an alternate" @close="callingIn = null">
        <form id="alternate-form" class="space-y-4" novalidate @submit.prevent="submitAlternate">
            <p class="text-sm text-slate-600">
                Covering for <strong>{{ callingIn?.member.name }}</strong> as
                {{ callingIn?.role_label }}<span v-if="callingIn?.room"> in Room {{ callingIn.room }}</span>.
            </p>

            <SelectInput
                v-if="availableAlternates.length"
                v-model="alternateForm.alternate_assignment_id"
                label="Alternate Examiner"
                required
                :options="availableAlternates.map((a) => ({ value: a.id, label: `${a.member.name} (${a.member.proctad_id})` }))"
                :error="alternateForm.errors.alternate_assignment_id"
            />
            <p v-else class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                No Alternate Examiner is on standby at this venue. Assign one in Step 2 first.
            </p>

            <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                They take over the designation and room above, and are recorded as present — so their
                certificate and evaluation reflect the role they actually served, not &ldquo;Alternate
                Examiner&rdquo;. This can be undone.
            </p>
        </form>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="callingIn = null">Cancel</BaseButton>
            <BaseButton
                type="submit"
                form="alternate-form"
                size="sm"
                :disabled="!availableAlternates.length"
                :loading="alternateForm.processing"
            >
                Call in
            </BaseButton>
        </template>
    </BaseModal>

    <!-- Force reassign modal -->
    <BaseModal :show="!!reassigning" title="Force Reassign" @close="reassigning = null">
        <form id="reassign-form" class="space-y-4" novalidate @submit.prevent="submitReassign">
            <p class="text-sm text-slate-600">
                <strong>{{ reassigning?.member.name }}</strong>
                <span class="ml-1 font-mono text-xs text-brand-700">{{ reassigning?.member.proctad_id }}</span>
            </p>
            <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                This admin override changes the role and venue only. The confirmation/approval status is
                preserved exactly as-is. The room link is cleared — re-run room staffing for the new venue.
            </p>
            <SelectInput v-model="reassignForm.role" label="New Role" required :options="roles" :error="reassignForm.errors.role" />
            <SelectInput
                v-model="reassignForm.examination_school_id"
                label="New Venue"
                required
                placeholder="Select a venue"
                :options="venueOptions"
                :error="reassignForm.errors.examination_school_id"
            />
        </form>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="reassigning = null">Cancel</BaseButton>
            <BaseButton
                type="submit"
                form="reassign-form"
                variant="primary"
                size="sm"
                :loading="reassignForm.processing"
                :disabled="reassignForm.processing"
            >
                Reassign
            </BaseButton>
        </template>
    </BaseModal>

    <!-- Bulk revoke confirm -->
    <BaseModal :show="revoking" title="Revoke selected designations" @close="revoking = false">
        <p class="text-sm leading-relaxed text-slate-600">
            Permanently revoke <strong>{{ selectedForRevoke.length }}</strong> assignment(s), regardless of their
            confirmation status? This bypasses the normal removal rules and cannot be undone.
        </p>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="revoking = false">Cancel</BaseButton>
            <BaseButton variant="accent" size="sm" :loading="revokeForm.processing" @click="submitBulkRevoke">
                Revoke {{ selectedForRevoke.length }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
