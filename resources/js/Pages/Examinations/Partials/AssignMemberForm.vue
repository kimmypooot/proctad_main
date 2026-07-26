<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import Tooltip from '@/Components/Tooltip.vue';
import { useVenueOptions } from '@/Composables/useVenueOptions';

// Named from the shared role labels so a rename at Administration → Roles is
// reflected here rather than leaving the old name in the hint below.
const foAdminLabel = computed(
    () => usePage().props.roleLabels?.fo_admin ?? 'Field Office Staff',
);

const props = defineProps({
    examination: { type: Object, required: true },
    assignableMembers: { type: Array, required: true },
    venues: { type: Array, required: true },
    roles: { type: Array, required: true },
    prefillVenueId: { type: [Number, String], default: null },
});

defineEmits(['jump-to-venues']);

const { venueOptions } = useVenueOptions(computed(() => props.venues));

const memberSearch = ref('');
const selected = ref([]);
const assignForm = useForm({ member_ids: [], role: '', examination_school_id: '', covered_school_ids: [] });

const isCoverageRole = computed(() => props.roles.find((r) => r.value === assignForm.role)?.is_coverage ?? false);

/*
 * Ex officio seats: REC Chair belongs to the Director IV, Co-Chair to the
 * Director III. The server rejects anyone else (see reservedSeatRule), so the
 * picker selects the right person rather than letting an admin choose someone
 * and only then be told no. Null when the post is vacant or its holder isn't
 * enrolled — the seat is genuinely open then, and the picker stays normal.
 */
const reservedMemberId = computed(
    () => props.roles.find((r) => r.value === assignForm.role)?.reserved_member_id ?? null,
);
const reservedMember = computed(() => (reservedMemberId.value
    ? props.assignableMembers.find((m) => m.id === reservedMemberId.value) ?? null
    : null));

watch(() => assignForm.role, (role, previous) => {
    const wasCoverage = props.roles.find((r) => r.value === previous)?.is_coverage ?? false;
    if (wasCoverage && !isCoverageRole.value) assignForm.covered_school_ids = [];

    if (reservedMemberId.value) selected.value = [reservedMemberId.value];
});

const toggleCoveredSchool = (id) => {
    const index = assignForm.covered_school_ids.indexOf(id);
    if (index === -1) {
        assignForm.covered_school_ids.push(id);
    } else {
        assignForm.covered_school_ids.splice(index, 1);
    }
};

watch(() => props.prefillVenueId, (venueId) => {
    if (venueId) assignForm.examination_school_id = venueId;
}, { immediate: true });

/*
 * The testing center of the venue currently chosen, or null when none is.
 * Members who also hold a field-office-scoped staff post (Field Office Staff,
 * Field Director) serve only where they work, so they drop out of the list
 * until a venue in one of their own centers is picked — the server enforces
 * this in staffJurisdictionRule; hiding them here just avoids offering a
 * choice that would be rejected.
 */
const selectedVenueCenterId = computed(() => props.venues
    .find((v) => v.id === Number(assignForm.examination_school_id))?.testing_center_id ?? null);

const isEligible = (member) => member.confined_to_center_ids === null
    || member.confined_to_center_ids.includes(selectedVenueCenterId.value);

const eligibleMembers = computed(() => props.assignableMembers.filter(isEligible));

// How many the venue choice is currently excluding, so the count below can say
// so rather than looking like people have gone missing.
const confinedOutCount = computed(
    () => props.assignableMembers.length - eligibleMembers.value.length,
);

// Changing the venue can strip eligibility from someone already ticked; without
// this their id would ride along in the payload and fail validation.
watch(selectedVenueCenterId, () => {
    const stillEligible = new Set(eligibleMembers.value.map((m) => m.id));
    selected.value = selected.value.filter((id) => stillEligible.has(id));
});

const filteredMembers = computed(() => {
    const term = memberSearch.value.toLowerCase();
    const pool = term
        ? eligibleMembers.value.filter((m) => m.label.toLowerCase().includes(term))
        : eligibleMembers.value;
    return pool.slice(0, 100);
});

const toggle = (id) => {
    const index = selected.value.indexOf(id);
    if (index === -1) {
        selected.value.push(id);
    } else {
        selected.value.splice(index, 1);
    }
};

const selectAllFiltered = () => {
    const ids = filteredMembers.value.map((m) => m.id);
    const allSelected = ids.every((id) => selected.value.includes(id));
    selected.value = allSelected
        ? selected.value.filter((id) => !ids.includes(id))
        : [...new Set([...selected.value, ...ids])];
};

const assign = () => assignForm
    .transform((data) => ({
        ...data,
        member_ids: selected.value,
        examination_school_id: data.examination_school_id || null,
        covered_school_ids: isCoverageRole.value ? data.covered_school_ids : [],
    }))
    .post(`/examinations/${props.examination.id}/assignments/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset();
            selected.value = [];
            memberSearch.value = '';
        },
    });
</script>

<template>
    <BaseCard padding="none" class="p-5">
        <h2 class="text-base font-semibold text-slate-900">Step 2 · Assign Members</h2>

        <p v-if="!venues.length" class="mt-2 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-500">
            No venues are attached yet. You can still assign members without a venue — appropriate for
            Regional Committee roles — but most Proctor/Examiner assignments should wait until a venue is set up.
            <button type="button" class="font-semibold text-brand-700 hover:underline" @click="$emit('jump-to-venues')">
                Go to Step 1 to add a venue →
            </button>
        </p>

        <form class="mt-4" @submit.prevent="assign">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput v-model="memberSearch" label="Search members" placeholder="Name or PROCTAD ID" class="lg:col-span-2" />
                <SelectInput
                    v-model="assignForm.role"
                    label="Role (applies to all selected)"
                    required
                    placeholder="Select role"
                    :options="roles"
                    :error="assignForm.errors.role"
                >
                    <template #label>
                        Role (applies to all selected)
                        <span class="text-accent-600">*</span>
                        <Tooltip text="REC, LEC, and CE for Investigation are 'coverage' roles — stationed at one venue but also responsible for monitoring other schools. REC roles are the only ones that can be assigned with no venue at all." wrap>
                            <span class="ml-1 inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-slate-300 text-[10px] font-bold text-white hover:bg-slate-400">?</span>
                        </Tooltip>
                    </template>
                </SelectInput>
                <SelectInput
                    v-model="assignForm.examination_school_id"
                    label="Venue"
                    optional
                    placeholder="Not yet assigned"
                    :options="venueOptions"
                    :error="assignForm.errors.examination_school_id"
                />
            </div>

            <p class="mt-2 text-xs text-slate-400">
                Only venue and role are assigned here — which room each Proctor/Room Examiner staffs is decided
                separately in Step 3, closer to the exam date.
            </p>

            <div v-if="isCoverageRole" class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-sm font-medium text-slate-700">Covered schools</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    REC/LEC roles are stationed at the one venue above but also monitor other schools —
                    check every school these members are responsible for. No confirmation is sent for
                    these; attendance is scanned/entered per school on exam day.
                </p>
                <div v-if="venueOptions.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1.5">
                    <label
                        v-for="venue in venueOptions"
                        :key="venue.value"
                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            type="checkbox"
                            :checked="assignForm.covered_school_ids.includes(venue.value)"
                            class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                            @change="toggleCoveredSchool(venue.value)"
                        >
                        {{ venue.label }}
                    </label>
                </div>
                <p v-else class="mt-2 text-xs text-slate-400">No venues attached yet — add venues in Step 1 first.</p>
            </div>

            <!--
                A reserved seat has exactly one rightful holder, already
                selected above. Showing the pool here would only invite a
                choice the server is going to reject.
            -->
            <div v-if="reservedMemberId" class="mt-4 rounded-lg border border-brand-200 bg-brand-50/60 px-3 py-2.5 text-sm text-brand-800">
                This designation is held ex officio by
                <span class="font-semibold">{{ reservedMember ? reservedMember.label : 'the incumbent director' }}</span>,
                who has been selected automatically.
            </div>

            <template v-else>
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                        {{ filteredMembers.length }} eligible member{{ filteredMembers.length === 1 ? '' : 's' }}
                        <span v-if="confinedOutCount" class="normal-case tracking-normal text-slate-400">
                            · {{ confinedOutCount }} hidden — {{ foAdminLabel }} serve only at their own center
                        </span>
                    </p>
                    <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="selectAllFiltered">
                        Select / clear all filtered
                    </button>
                </div>

                <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-slate-200">
                    <label
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2 text-sm last:border-b-0 hover:bg-slate-50"
                    >
                        <input
                            type="checkbox"
                            :checked="selected.includes(member.id)"
                            class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                            @change="toggle(member.id)"
                        >
                        <span class="text-slate-700">{{ member.label }}</span>
                    </label>
                    <p v-if="!filteredMembers.length" class="px-3 py-4 text-center text-sm text-slate-400">
                        No eligible members match your search.
                    </p>
                </div>
            </template>
            <p v-if="assignForm.errors.member_ids" class="mt-1.5 text-sm text-accent-600" role="alert">{{ assignForm.errors.member_ids }}</p>

            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ selected.length }} member{{ selected.length === 1 ? '' : 's' }} selected</p>
                <BaseButton
                    type="submit"
                    variant="secondary"
                    size="sm"
                    :loading="assignForm.processing"
                    :disabled="assignForm.processing || !selected.length"
                >
                    Assign {{ selected.length || '' }} Member{{ selected.length === 1 ? '' : 's' }}
                </BaseButton>
            </div>
        </form>
    </BaseCard>
</template>
