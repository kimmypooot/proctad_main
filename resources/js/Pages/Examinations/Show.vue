<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import StepTabs from '@/Components/StepTabs.vue';
import AssignMemberForm from './Partials/AssignMemberForm.vue';
import AssignmentsTable from './Partials/AssignmentsTable.vue';
import ReportGenerationPanel from './Partials/ReportGenerationPanel.vue';
import RoomAssignmentPanel from './Partials/RoomAssignmentPanel.vue';
import RosterReview from './Partials/RosterReview.vue';
import VenuesRoomsPanel from './Partials/VenuesRoomsPanel.vue';

const props = defineProps({
    examination: { type: Object, required: true },
    assignments: { type: Array, required: true },
    assignableMembers: { type: Array, required: true },
    venues: { type: Array, required: true },
    availableSchools: { type: Array, required: true },
    availableNep: { type: Array, required: true },
    roles: { type: Array, required: true },
    ratings: { type: Array, required: true },
    can: { type: Object, required: true },
});

const storageKey = `examStep:${props.examination.id}`;
const validStepKeys = ['venues', 'assign', 'rooms', 'review', 'report'];

const defaultStep = props.venues.length === 0 && props.can.manageVenues ? 'venues' : 'assign';
const persisted = typeof window !== 'undefined' ? window.localStorage.getItem(storageKey) : null;

// A `?step=` query param (e.g. from Manage Rooms' back link) always wins over the
// persisted/default step, so returning from a drill-down page is deterministic
// rather than depending on whatever was last saved to localStorage.
const requestedStep = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('step') : null;
const initialStep = validStepKeys.includes(requestedStep) ? requestedStep : (persisted ?? defaultStep);
const currentStep = ref(initialStep);
const prefillVenueId = ref(null);

if (typeof window !== 'undefined' && requestedStep) {
    const url = new URL(window.location.href);
    url.searchParams.delete('step');
    window.history.replaceState(window.history.state, '', url);
}

const assignVenue = (venueId) => {
    prefillVenueId.value = venueId;
    currentStep.value = 'assign';
};

watch(currentStep, (step) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, step);
    }
});

const steps = computed(() => [
    props.can.manageVenues && {
        key: 'venues',
        label: 'Schools & Rooms',
        description: 'Attach schools, manage rooms',
        hint: 'Attach the schools serving as testing venues, then open each school\'s Manage Rooms workspace to define rooms and staffing.',
        complete: props.venues.length > 0,
    },
    {
        key: 'assign',
        label: 'Assign Members',
        description: 'Deploy PROCTAD members',
        hint: props.venues.length === 0
            ? 'No venues yet — Regional Committee roles can still be assigned without one, but most Proctor/Examiner assignments should wait until Step 1 is done.'
            : 'Assign PROCTAD members to this examination and their role. Venue/room can be left blank for Regional Committee roles.',
        complete: props.assignments.length > 0,
    },
    {
        key: 'rooms',
        label: 'Assign Rooms',
        description: 'Pick a school to manage its rooms',
        hint: props.assignments.length === 0
            ? 'This step unlocks once at least one member is assigned in Step 2.'
            : 'Pick a school below to manage its rooms and room-level staffing. Requires rooms from Step 1 and members from Step 2.',
        complete: props.venues.some((v) => v.rooms.length > 0)
            && props.assignments.some((a) => a.exam_room_id),
    },
    {
        key: 'review',
        label: 'Review Roster',
        description: 'Final check before exam day',
        hint: 'Final sanity check before exam day — confirm every assignment, including room-level staffing from Step 3. This is the best place to catch late withdrawals or reassignments.',
        complete: props.assignments.length > 0
            && props.assignments.every((a) => a.status === 'confirmed'),
    },
    {
        key: 'report',
        label: 'Generate Reports',
        description: 'Room assignment, payroll, payroll posting',
        hint: 'Generate the final printable and payroll documents for this examination — Room Assignment, Payroll, and Payroll Posting. Each has its own filters and downloads directly as an Excel file.',
        complete: false,
    },
].filter(Boolean));

const jumpTo = (key) => (currentStep.value = key);
</script>

<template>
    <Head :title="examination.title" />

    <DashboardLayout>
        <DashboardPageHeader :title="examination.title">
            <template #back>
                <BaseButton href="/examinations" variant="link">← Examinations</BaseButton>
            </template>
            <template #badges>
                <BaseBadge v-if="examination.upcoming" variant="brand">Upcoming</BaseBadge>
                <span class="text-sm text-slate-500">
                    {{ examination.type }} · {{ examination.exam_date }}
                    <template v-if="venues.length"> · {{ venues.map((v) => v.school_name).join(', ') }}</template>
                </span>
            </template>
            <template v-if="can.assign" #actions>
                <BaseButton :href="`/scanner?examination_id=${examination.id}`" variant="primary" size="sm">
                    <AppIcon name="qr-code" class="h-4 w-4" />
                    Scan Attendance
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:px-5">
            <StepTabs v-model="currentStep" :steps="steps" />
        </div>

        <VenuesRoomsPanel
            v-if="currentStep === 'venues' && can.manageVenues"
            class="mt-6"
            :examination="examination"
            :venues="venues"
            :available-schools="availableSchools"
            :available-nep="availableNep"
            :can="can"
            @advance="currentStep = 'assign'"
            @assign-venue="assignVenue"
        />

        <div v-else-if="currentStep === 'assign'" class="mt-6 space-y-6">
            <AssignMemberForm
                v-if="can.assign"
                :examination="examination"
                :assignable-members="assignableMembers"
                :venues="venues"
                :roles="roles"
                :prefill-venue-id="prefillVenueId"
                @jump-to-venues="jumpTo('venues')"
            />

            <AssignmentsTable
                :examination="examination"
                :assignments="assignments"
                :venues="venues"
                :roles="roles"
                :ratings="ratings"
                :can="can"
            />
        </div>

        <RoomAssignmentPanel
            v-else-if="currentStep === 'rooms'"
            class="mt-6"
            :examination="examination"
            :venues="venues"
            :can="can"
        />

        <RosterReview
            v-else-if="currentStep === 'review'"
            class="mt-6"
            :examination="examination"
            :assignments="assignments"
            :can="can"
        />

        <ReportGenerationPanel
            v-else-if="currentStep === 'report'"
            class="mt-6"
            :examination="examination"
            :venues="venues"
        />
    </DashboardLayout>
</template>
