<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    records: { type: Array, required: true },
});

const completedCount = computed(() => props.records.filter((r) => r.completed).length);
const confirmedCount = computed(() => props.records.filter((r) => r.attended).length);

/* --- Filters (client-side; the list is scoped to the logged-in member) --- */
const search = ref('');
const typeFilter = ref('');
const statusFilter = ref('');

const typeOptions = computed(() => {
    const seen = new Set();
    props.records.forEach((r) => r.type_label && seen.add(r.type_label));
    return [...seen].map((label) => ({ value: label, label }));
});

const filteredRecords = computed(() => props.records.filter((r) => {
    if (typeFilter.value && r.type_label !== typeFilter.value) return false;
    if (statusFilter.value === 'completed' && !r.completed) return false;
    if (statusFilter.value === 'scheduled' && r.completed) return false;
    if (search.value) {
        const needle = search.value.toLowerCase();
        const haystack = `${r.title ?? ''} ${r.venue ?? ''}`.toLowerCase();
        if (!haystack.includes(needle)) return false;
    }
    return true;
}));

const hasActiveFilters = computed(() => !!(search.value || typeFilter.value || statusFilter.value));

const resetFilters = () => {
    search.value = '';
    typeFilter.value = '';
    statusFilter.value = '';
};

/* --- View modal --- */
const viewing = ref(null);
const viewRecord = (record) => (viewing.value = record);
const closeViewer = () => (viewing.value = null);
</script>

<template>
    <Head title="My Trainings" />

    <DashboardLayout>
        <DashboardPageHeader title="My Trainings" subtitle="Training sessions and briefings you've been enrolled in." />

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="academic-cap"
                title="No PROCTAD record linked to your account"
                description="Your training records become available once your Field Office registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="records.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Trainings" :value="records.length" icon="academic-cap" />
                <StatCard compact label="Completed" :value="completedCount" icon="check-badge" accent="emerald" />
                <StatCard compact label="Attendance Confirmed" :value="confirmedCount" icon="check-circle" />
            </div>

            <!-- Filters -->
            <BaseCard padding="sm" class="mt-6 grid gap-4 sm:grid-cols-3">
                <TextInput
                    v-model="search"
                    label="Search"
                    icon="magnifying-glass"
                    placeholder="Training title or venue"
                />
                <SelectInput
                    v-model="typeFilter"
                    label="Type"
                    placeholder="All types"
                    :options="[{ value: '', label: 'All types' }, ...typeOptions]"
                />
                <SelectInput
                    v-model="statusFilter"
                    label="Status"
                    placeholder="All statuses"
                    :options="[{ value: '', label: 'All statuses' }, { value: 'completed', label: 'Completed' }, { value: 'scheduled', label: 'Scheduled' }]"
                />
            </BaseCard>
            <div v-if="hasActiveFilters" class="mt-2 flex items-center justify-between text-sm text-slate-500">
                <span>{{ filteredRecords.length }} of {{ records.length }} training(s)</span>
                <BaseButton variant="link" size="sm" @click="resetFilters">Clear filters</BaseButton>
            </div>

            <BaseTable
                v-if="filteredRecords.length"
                class="mt-6"
                :columns="[
                    { label: 'Training' },
                    { label: 'Type', class: 'hidden sm:table-cell' },
                    { label: 'Date' },
                    { label: 'Attendance', class: 'hidden sm:table-cell' },
                    { label: 'Status' },
                    { label: 'Actions', class: 'w-10', align: 'center' },
                ]"
            >
                        <tr v-for="record in filteredRecords" :key="record.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2 font-medium text-slate-900">
                                {{ record.title }}
                                <p class="text-xs font-normal text-slate-400 sm:hidden">{{ record.type_label }}</p>
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ record.type_label }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ record.date }}</td>
                            <td class="hidden px-3 py-2 sm:table-cell">
                                <span v-if="record.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                    <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed
                                </span>
                                <span v-else class="text-slate-400">Not confirmed</span>
                            </td>
                            <td class="px-3 py-2">
                                <BaseBadge :variant="record.completed ? 'success' : 'warning'">
                                    {{ record.completed ? 'Completed' : 'Scheduled' }}
                                </BaseBadge>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <IconButton icon="eye" label="View details" @click="viewRecord(record)" />
                            </td>
                        </tr>
            </BaseTable>
            <div v-else class="mt-6">
                <EmptyState
                    icon="academic-cap"
                    title="No trainings match your filters"
                    description="Try adjusting or clearing the filters above."
                />
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="academic-cap"
                title="No training records yet"
                description="Trainings you're enrolled in will appear here."
            />
        </div>

        <!-- View training modal -->
        <BaseModal :show="!!viewing" title="Training Details" max-width="lg" @close="closeViewer">
            <div v-if="viewing" class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ viewing.type_label }}</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ viewing.title }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Date</p>
                        <p class="mt-0.5 text-sm text-slate-800">
                            {{ viewing.date }}<template v-if="viewing.end_date"> – {{ viewing.end_date }}</template>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Venue</p>
                        <p class="mt-0.5 text-sm text-slate-800">{{ viewing.venue ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Attendance</p>
                        <p class="mt-0.5 text-sm text-slate-800">
                            <span v-if="viewing.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed{{ viewing.attendance_confirmed_at ? ` — ${viewing.attendance_confirmed_at}` : '' }}
                            </span>
                            <span v-else class="text-slate-400">Not confirmed</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Status</p>
                        <p class="mt-0.5">
                            <BaseBadge :variant="viewing.completed ? 'success' : 'warning'">
                                {{ viewing.completed ? 'Completed' : 'Scheduled' }}
                            </BaseBadge>
                            <span v-if="viewing.completed_at" class="ml-2 text-xs text-slate-400">{{ viewing.completed_at }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="closeViewer">Close</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
