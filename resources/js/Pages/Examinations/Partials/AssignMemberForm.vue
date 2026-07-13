<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useVenueOptions } from '@/Composables/useVenueOptions';

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

watch(() => assignForm.role, (role, previous) => {
    const wasCoverage = props.roles.find((r) => r.value === previous)?.is_coverage ?? false;
    if (wasCoverage && !isCoverageRole.value) assignForm.covered_school_ids = [];
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

const filteredMembers = computed(() => {
    const term = memberSearch.value.toLowerCase();
    const pool = term
        ? props.assignableMembers.filter((m) => m.label.toLowerCase().includes(term))
        : props.assignableMembers;
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
    <div class="rounded-xl border border-slate-200 bg-white p-5">
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
                />
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

            <div class="mt-4 flex items-center justify-between">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    {{ filteredMembers.length }} eligible member{{ filteredMembers.length === 1 ? '' : 's' }}
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
    </div>
</template>
