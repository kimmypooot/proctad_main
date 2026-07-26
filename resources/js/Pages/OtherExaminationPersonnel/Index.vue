<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ViewOepModal from './Partials/ViewOepModal.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BasePagination from '@/Components/BasePagination.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    personnel: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    fieldOffices: { type: Array, default: null },
    testingCenters: { type: Array, default: () => [] },
    personnelTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const viewingOepId = ref(null);
const showOepModal = ref(false);

const viewOep = (id) => {
    viewingOepId.value = id;
    showOepModal.value = true;
};

const search = ref(props.filters.search ?? '');
const personnelType = ref(props.filters.personnel_type ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const testingCenterId = ref(props.filters.testing_center_id ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/other-examination-personnel', {
        search: search.value || undefined,
        personnel_type: personnelType.value || undefined,
        field_office_id: fieldOfficeId.value || undefined,
        testing_center_id: testingCenterId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['personnel', 'filters'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([personnelType, fieldOfficeId, testingCenterId], applyFilters);
</script>

<template>
    <Head title="Other Examination Personnel" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Other Examination Personnel"
            subtitle="Coordinators, inspectors, PNP officers, and other support staff for examination venues."
        >
            <template v-if="can.create" #actions>
                <BaseButton href="/other-examination-personnel/create" variant="primary" size="sm" icon="user-plus">
                    Add Personnel
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Filters -->
        <BaseCard padding="sm" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TextInput v-model="search" label="Search" placeholder="OEP ID, name, or agency" />
            <SelectInput
                v-model="personnelType"
                label="Personnel Type"
                placeholder="All types"
                :options="[{ value: '', label: 'All types' }, ...personnelTypes]"
            />
            <SelectInput
                v-if="testingCenters.length"
                v-model="testingCenterId"
                label="Testing Center"
                placeholder="All testing centers"
                :options="[{ value: '', label: 'All testing centers' }, ...testingCenters.map((tc) => ({ value: tc.id, label: tc.name }))]"
            />
            <SelectInput
                v-if="fieldOffices"
                v-model="fieldOfficeId"
                label="Field Office"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
        </BaseCard>

        <!-- Results -->
        <BaseTable
            v-if="loading || personnel.data.length"
            class="mt-6"
            :loading="loading"
            :skeleton-columns="6"
            :columns="[
                { label: 'OEP ID' },
                { label: 'Name' },
                { label: 'Type', class: 'hidden sm:table-cell' },
                { label: 'Testing Center', class: 'hidden md:table-cell' },
                { label: 'Field Office', class: 'hidden lg:table-cell' },
                { label: 'Status' },
            ]"
        >
                    <tr v-for="oep in personnel.data" :key="oep.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold text-brand-700">
                            <button @click="viewOep(oep.id)" class="hover:underline">{{ oep.oep_id }}</button>
                        </td>
                        <td class="px-3 py-2 font-medium text-slate-900">
                            <button @click="viewOep(oep.id)" class="text-left hover:underline">{{ oep.name }}</button>
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ oep.personnel_type_label }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ oep.personnel_type_label }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">
                            <span v-if="oep.testing_center">{{ oep.testing_center.name }}</span>
                            <span v-else-if="oep.is_region_wide" class="text-slate-500">Region-wide</span>
                            <span v-else class="text-accent-600">Not placed</span>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 lg:table-cell">{{ oep.field_office?.name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="oep.is_active ? 'success' : 'neutral'">
                                {{ oep.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                    </tr>
        </BaseTable>

        <div v-else class="mt-6">
            <EmptyState
                icon="user-group"
                title="No personnel found"
                description="No other examination personnel match your search or filters."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="personnel.links" />
        </div>

        <ViewOepModal
            :show="showOepModal"
            :oep-id="viewingOepId"
            @close="showOepModal = false"
        />
    </DashboardLayout>
</template>
