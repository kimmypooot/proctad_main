<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    assignments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    examinations: { type: Array, required: true },
    fieldOffices: { type: Array, default: null },
    roles: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const examinationId = ref(props.filters.examination_id ?? '');
const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');
const search = ref(props.filters.search ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/evaluation-monitoring', {
        examination_id: examinationId.value || undefined,
        field_office_id: fieldOfficeId.value || undefined,
        role: role.value || undefined,
        status: status.value || undefined,
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['assignments', 'filters', 'summary'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([examinationId, fieldOfficeId, role, status], applyFilters);
</script>

<template>
    <Head title="Evaluation Monitoring" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Evaluation Monitoring"
            subtitle="Track who has submitted a Post-Examination Evaluation."
        />

        <!-- Summary -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total Eligible" :value="summary.total" />
            <StatCard label="Submitted" :value="summary.submitted" accent="emerald" />
            <StatCard label="Not Submitted" :value="summary.not_submitted" accent="amber" />
            <StatCard label="Completion Rate" :value="`${summary.percentage}%`" accent="brand" />
        </div>

        <!-- Filters -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
            <SelectInput
                v-model="examinationId"
                label="Examination"
                placeholder="Select an examination"
                :options="examinations.map((e) => ({ value: e.id, label: `${e.title} — ${e.exam_date}` }))"
            />
            <SelectInput
                v-if="fieldOffices"
                v-model="fieldOfficeId"
                label="Field Office"
                placeholder="All field offices"
                :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: fo.id, label: fo.name }))]"
            />
            <SelectInput
                v-model="role"
                label="Designation"
                placeholder="All designations"
                :options="[{ value: '', label: 'All designations' }, ...roles]"
            />
            <SelectInput
                v-model="status"
                label="Status"
                placeholder="All statuses"
                :options="[
                    { value: '', label: 'All statuses' },
                    { value: 'submitted', label: 'Submitted' },
                    { value: 'not_submitted', label: 'Not Submitted' },
                ]"
            />
            <TextInput v-model="search" label="Search" placeholder="Name or PROCTAD ID" />
        </div>

        <!-- Results -->
        <div v-if="loading || assignments.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Member</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Field Office</th>
                        <th class="px-3 py-2">Designation</th>
                        <th class="hidden px-3 py-2 md:table-cell">Venue / Room</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="6" />
                    <tr v-for="assignment in loading ? [] : assignments.data" :key="assignment.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-900">{{ assignment.member.name }}</p>
                            <p class="font-mono text-xs text-brand-700">{{ assignment.member.proctad_id }}</p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ assignment.field_office?.name ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ assignment.role_label }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 md:table-cell">
                            <template v-if="assignment.venue">
                                {{ assignment.venue }}<span v-if="assignment.room"> — {{ assignment.room }}</span>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="assignment.has_submitted ? 'success' : 'warning'">
                                {{ assignment.has_submitted ? 'Submitted' : 'Not Submitted' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2">
                            <Link
                                v-if="assignment.has_submitted"
                                :href="`/evaluation-monitoring/${assignment.evaluation_id}`"
                                class="text-sm font-medium text-brand-700 hover:underline"
                            >
                                View
                            </Link>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="clipboard-check"
                title="No assignments found"
                description="Select an examination, or adjust your filters, to see evaluation status."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="assignments.links" />
        </div>
    </DashboardLayout>
</template>
