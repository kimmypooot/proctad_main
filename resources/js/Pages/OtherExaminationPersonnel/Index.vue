<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    personnel: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    fieldOffices: { type: Array, default: null },
    personnelTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const personnelType = ref(props.filters.personnel_type ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/other-examination-personnel', {
        search: search.value || undefined,
        personnel_type: personnelType.value || undefined,
        field_office_id: fieldOfficeId.value || undefined,
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
watch([personnelType, fieldOfficeId], applyFilters);
</script>

<template>
    <Head title="Other Examination Personnel" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Other Examination Personnel"
            subtitle="Coordinators, inspectors, PNP officers, and other support staff for examination venues."
        >
            <template v-if="can.create" #actions>
                <BaseButton href="/other-examination-personnel/create" variant="primary" size="sm">
                    <AppIcon name="user-plus" class="h-4 w-4" />
                    Add Personnel
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Filters -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
            <TextInput v-model="search" label="Search" placeholder="OEP ID, name, or agency" />
            <SelectInput
                v-model="personnelType"
                label="Personnel Type"
                placeholder="All types"
                :options="[{ value: '', label: 'All types' }, ...personnelTypes]"
            />
            <SelectInput
                v-if="fieldOffices"
                v-model="fieldOfficeId"
                label="Testing Center"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
        </div>

        <!-- Results -->
        <div v-if="loading || personnel.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">OEP ID</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                        <th class="hidden px-3 py-2 md:table-cell">Testing Center</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="5" />
                    <tr v-for="oep in loading ? [] : personnel.data" :key="oep.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold text-brand-700">
                            <Link :href="`/other-examination-personnel/${oep.id}`" class="hover:underline">{{ oep.oep_id }}</Link>
                        </td>
                        <td class="px-3 py-2 font-medium text-slate-900">
                            <Link :href="`/other-examination-personnel/${oep.id}`" class="hover:underline">{{ oep.name }}</Link>
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ oep.personnel_type_label }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ oep.personnel_type_label }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 md:table-cell">{{ oep.field_office?.name ?? 'Region-wide' }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="oep.is_active ? 'success' : 'neutral'">
                                {{ oep.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
    </DashboardLayout>
</template>
