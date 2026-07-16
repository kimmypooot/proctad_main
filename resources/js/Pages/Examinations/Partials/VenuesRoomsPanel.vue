<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    examination: { type: Object, required: true },
    venues: { type: Array, required: true },
    availableSchools: { type: Array, required: true },
    availableOep: { type: Array, required: true },
    can: { type: Object, required: true },
});

const emit = defineEmits(['advance', 'assign-venue']);

/* --- Add venue modal --- */
const showAddVenue = ref(false);
const venueForm = useForm({ school_id: '' });
const openAddVenue = () => {
    venueForm.reset();
    venueForm.clearErrors();
    showAddVenue.value = true;
};
const addVenue = () => venueForm.post(`/examinations/${props.examination.id}/venues`, {
    preserveScroll: true,
    onSuccess: () => (showAddVenue.value = false),
});

/* --- Overall stats --- */
const totalRooms = computed(() => props.venues.reduce((sum, v) => sum + v.rooms_count, 0));
const totalRequired = computed(() => props.venues.reduce((sum, v) => sum + v.staffing.required_total, 0));
const totalAssigned = computed(() => props.venues.reduce((sum, v) => sum + v.staffing.assigned_total, 0));
const overallRatio = computed(() => (totalRequired.value > 0 ? Math.round((totalAssigned.value / totalRequired.value) * 100) : null));

/* --- Per-venue staffing progress color, matching legacy thresholds --- */
const progressVariant = (venue) => {
    const ratio = venue.staffing.ratio;
    if (!venue.rooms_count || !ratio) return 'neutral';
    if (ratio >= 100) return 'success';
    if (ratio >= 50) return 'warning';
    return 'accent';
};
const progressBarClass = (venue) => ({
    neutral: 'bg-slate-300',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    accent: 'bg-accent-500',
}[progressVariant(venue)]);

/* --- Remove venue --- */
const removingVenue = ref(null);
const removeVenueForm = useForm({});
const confirmRemoveVenue = () => removeVenueForm.delete(`/venues/${removingVenue.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (removingVenue.value = null),
});

/* --- OEP assignments (kept below each card, as before) --- */
const oepFormFor = ref(null);
const oepForm = useForm({ other_examination_personnel_id: '' });
const openOepForm = (venue) => {
    oepFormFor.value = venue;
    oepForm.reset();
    oepForm.clearErrors();
};
const addOep = () => oepForm.post(`/venues/${oepFormFor.value.id}/oep-assignments`, {
    preserveScroll: true,
    onSuccess: () => (oepFormFor.value = null),
});

const removingOep = ref(null);
const removeOepForm = useForm({});
const confirmRemoveOep = () => removeOepForm.delete(`/oep-assignments/${removingOep.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (removingOep.value = null),
});

const attendanceForm = useForm({});
const toggleOepAttendance = (oepAssignment) => attendanceForm
    .transform(() => ({ present: !oepAssignment.present }))
    .patch(`/oep-assignments/${oepAssignment.id}/attendance`, { preserveScroll: true });

const OEP_GROUP_ORDER = ['Testing Committee', 'Support Personnel'];
const groupOepAssignments = (assignments) => {
    const groups = {};
    for (const assignment of assignments) {
        const label = assignment.role_group_label ?? 'Other';
        (groups[label] ??= []).push(assignment);
    }
    const orderedLabels = [
        ...OEP_GROUP_ORDER.filter((label) => groups[label]),
        ...Object.keys(groups).filter((label) => !OEP_GROUP_ORDER.includes(label)),
    ];
    return orderedLabels.map((label) => ({ label, items: groups[label] }));
};
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Step 1 · Schools & Rooms</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Attach the schools serving as testing venues for this examination, then manage each
                        school's rooms and staffing from its own workspace.
                    </p>
                </div>
                <BaseButton v-if="can.manageVenues" variant="primary" size="sm" @click="openAddVenue">
                    <AppIcon name="plus" class="h-4 w-4" />
                    Add School
                </BaseButton>
            </div>
        </div>

        <!-- Stats grid -->
        <div v-if="venues.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Schools / Venues" :value="venues.length" icon="building-office" />
            <StatCard label="Total Rooms" :value="totalRooms" icon="identification" />
            <StatCard label="Staff Assigned" :value="`${totalAssigned} / ${totalRequired}`" icon="users" />
            <StatCard label="Overall Staffing" :value="overallRatio === null ? '—' : `${overallRatio}%`" icon="chart-bar" />
        </div>

        <!-- School cards -->
        <div v-if="venues.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="venue in venues" :key="venue.id" class="flex flex-col rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ venue.school_name }}</p>
                        <p v-if="venue.municipality" class="text-xs text-slate-400">{{ venue.municipality }}</p>
                    </div>
                    <IconButton icon="trash" label="Remove School" variant="danger" class="shrink-0" @click="removingVenue = venue" />
                </div>

                <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
                    <template v-if="venue.rooms_count">
                        <span>{{ venue.rooms_count }} room{{ venue.rooms_count === 1 ? '' : 's' }}</span>
                        <span>· {{ venue.total_capacity }} seats</span>
                    </template>
                    <BaseBadge v-else variant="warning">
                        <AppIcon name="exclamation-triangle" class="h-3.5 w-3.5" />
                        No rooms yet — add rooms before assigning Proctor/Examiner roles
                    </BaseBadge>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-500">Staffing</span>
                        <span class="text-slate-400">{{ venue.staffing.assigned_total }} / {{ venue.staffing.required_total }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="progressBarClass(venue)"
                            :style="{ width: `${venue.staffing.ratio ?? 0}%` }"
                        />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <BaseBadge :variant="progressVariant(venue)" size="xs">{{ venue.staffing.assigned.proctor }} Proctor(s)</BaseBadge>
                    <BaseBadge :variant="progressVariant(venue)" size="xs">{{ venue.staffing.assigned.room_examiner }} Examiner(s)</BaseBadge>
                    <BaseBadge :variant="progressVariant(venue)" size="xs">{{ venue.staffing.assigned.supervising_examiner }} Supervisor(s)</BaseBadge>
                </div>

                <div v-if="venue.oep_assignments.length" class="mt-4 border-t border-slate-100 pt-3">
                    <p class="text-[0.7rem] font-medium uppercase tracking-wide text-slate-400">Other Examination Personnel</p>
                    <div v-for="group in groupOepAssignments(venue.oep_assignments)" :key="group.label" class="mt-2">
                        <p class="text-[0.65rem] font-semibold text-slate-500">{{ group.label }}</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="oepAssignment in group.items"
                                :key="oepAssignment.id"
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="oepAssignment.present ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                            >
                                <button type="button" class="inline-flex items-center gap-1" @click="toggleOepAttendance(oepAssignment)">
                                    <AppIcon v-if="oepAssignment.present" name="check-circle" class="h-3 w-3" />
                                    {{ oepAssignment.name }}
                                </button>
                                <button type="button" class="text-accent-500 hover:text-accent-700" @click="removingOep = oepAssignment">
                                    <AppIcon name="x-mark" class="h-3 w-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    <BaseButton :href="`/venues/${venue.id}/rooms`" variant="outline" size="sm">
                        <AppIcon name="building-library" class="h-4 w-4" />
                        Manage Rooms
                    </BaseButton>
                    <BaseButton variant="primary" size="sm" @click="emit('assign-venue', venue.id)">
                        Assign PROCTAD
                    </BaseButton>
                    <BaseButton v-if="can.manageOep" variant="link" @click="openOepForm(venue)">
                        <AppIcon name="plus" class="h-3.5 w-3.5" /> Add Personnel
                    </BaseButton>
                </div>
            </div>

            <!-- Next-step nudge -->
            <div class="flex items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 md:col-span-2 xl:col-span-3">
                <p class="text-sm text-brand-800">Schools are set up. Ready to assign PROCTAD members?</p>
                <BaseButton variant="primary" size="sm" @click="emit('advance')">
                    Next: Assign Members
                    <AppIcon name="arrow-right" class="h-4 w-4" />
                </BaseButton>
            </div>
        </div>

        <EmptyState
            v-else
            icon="building-office"
            title="No Schools Configured"
            description="Add schools or testing venues for this examination to begin assigning PROCTAD members."
        >
            <template v-if="can.manageVenues" #action>
                <BaseButton variant="primary" size="sm" @click="openAddVenue">
                    <AppIcon name="plus" class="h-4 w-4" />
                    Add First School
                </BaseButton>
            </template>
        </EmptyState>

        <p v-if="can.manageVenues && !availableSchools.length && !venues.length" class="text-xs text-amber-600">
            No active schools are available to attach. Register a school first under
            <a href="/schools" class="font-medium underline">Testing Center → Schools</a>.
        </p>

        <!-- Add school modal -->
        <BaseModal :show="showAddVenue" title="Add School" @close="showAddVenue = false">
            <form id="venue-form" class="space-y-4" novalidate @submit.prevent="addVenue">
                <SelectInput
                    v-model="venueForm.school_id"
                    label="School"
                    required
                    placeholder="Select a school"
                    :options="availableSchools"
                    :error="venueForm.errors.school_id"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showAddVenue = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="venue-form"
                    variant="primary"
                    size="sm"
                    :loading="venueForm.processing"
                    :disabled="venueForm.processing || !venueForm.school_id"
                >
                    Add School
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Add OEP modal -->
        <BaseModal :show="!!oepFormFor" title="Add Other Examination Personnel" @close="oepFormFor = null">
            <form id="oep-form" class="space-y-4" novalidate @submit.prevent="addOep">
                <p class="text-sm text-slate-600">School: <strong>{{ oepFormFor?.school_name }}</strong></p>
                <SelectInput
                    v-model="oepForm.other_examination_personnel_id"
                    label="Person"
                    required
                    placeholder="Select a person"
                    :options="availableOep"
                    :error="oepForm.errors.other_examination_personnel_id"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="oepFormFor = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="oep-form"
                    variant="primary"
                    size="sm"
                    :loading="oepForm.processing"
                    :disabled="oepForm.processing"
                >
                    Add
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Remove OEP confirm -->
        <BaseModal :show="!!removingOep" title="Remove personnel" @close="removingOep = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ removingOep?.name }}</strong> from this school?
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="removingOep = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="removeOepForm.processing" @click="confirmRemoveOep">
                    Remove
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Remove venue confirm -->
        <BaseModal :show="!!removingVenue" title="Remove school" @close="removingVenue = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ removingVenue?.school_name }}</strong> as a testing venue? This also removes all
                its rooms and any staffing linked to those rooms.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="removingVenue = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="removeVenueForm.processing" @click="confirmRemoveVenue">
                    Remove
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
