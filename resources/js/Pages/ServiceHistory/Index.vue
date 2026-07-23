<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextInput from '@/Components/TextInput.vue';
import ViewServiceHistoryModal from './Partials/ViewServiceHistoryModal.vue';

const props = defineProps({
    members: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    fieldOffices: { type: Array, default: null },
    roles: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const fieldOfficeId = ref(props.filters.field_office_id ?? '');
const role = ref(props.filters.role ?? '');
const search = ref(props.filters.search ?? '');
const loading = ref(false);

let debounce = null;

const applyFilters = () => {
    router.get('/service-history', {
        field_office_id: fieldOfficeId.value || undefined,
        role: role.value || undefined,
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['members', 'filters', 'summary'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});
watch([fieldOfficeId, role], applyFilters);

const viewingMemberId = ref(null);
const showViewModal = ref(false);
const viewMember = (id) => {
    viewingMemberId.value = id;
    showViewModal.value = true;
};
</script>

<template>
    <Head title="Service History" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Service History"
            subtitle="Track examinations served by Test Administrators — designations, venues, and deployment history."
        />

        <!-- Summary -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Test Administrators" :value="summary.total_administrators" icon="user-group" />
            <StatCard label="Examinations Served" :value="summary.total_served" icon="clipboard-check" accent="emerald" />
            <StatCard label="Most Recent Assignment" icon="clock" accent="brand">
                <span class="line-clamp-2 text-base leading-snug">{{ summary.most_recent?.title ?? '—' }}</span>
                <span v-if="summary.most_recent?.date" class="mt-1 block text-xs font-normal text-slate-400">
                    {{ summary.most_recent.date }}
                </span>
            </StatCard>
            <StatCard compact label="Services by Designation" icon="academic-cap" accent="amber">
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
                    <span v-for="d in summary.by_designation" :key="d.label">
                        {{ d.label }}: <strong class="text-slate-800">{{ d.count }}</strong>
                    </span>
                </div>
            </StatCard>
        </div>

        <!-- Filters -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
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
            <TextInput v-model="search" label="Search" placeholder="Name or PROCTAD ID" />
        </div>

        <!-- Results -->
        <div v-if="loading || members.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Test Administrator</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Field Office</th>
                        <th class="px-3 py-2">Designations Served</th>
                        <th class="hidden px-3 py-2 md:table-cell">Most Recent Assignment</th>
                        <th class="px-3 py-2 text-center">Total Served</th>
                        <th class="px-3 py-2">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="6" />
                    <tr v-for="member in loading ? [] : members.data" :key="member.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-900">{{ member.name }}</p>
                            <p class="font-mono text-xs text-brand-700">{{ member.proctad_id }}</p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ member.field_office?.name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                <BaseBadge v-for="d in member.designations" :key="d.role" variant="neutral" size="xs">
                                    {{ d.label }} ({{ d.count }})
                                </BaseBadge>
                            </div>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 md:table-cell">
                            <template v-if="member.latest">
                                <p>{{ member.latest.exam_title }}</p>
                                <p class="text-xs text-slate-400">{{ member.latest.exam_date }} — {{ member.latest.role_label }}</p>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2 text-center font-semibold text-slate-800">{{ member.total_served }}</td>
                        <td class="px-3 py-2">
                            <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="viewMember(member.id)">
                                View
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="clock"
                title="No service history found"
                description="Adjust your filters, or check back once Test Administrators have confirmed attendance for an examination."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="members.links" />
        </div>

        <ViewServiceHistoryModal
            :show="showViewModal"
            :member-id="viewingMemberId"
            @close="showViewModal = false"
        />
    </DashboardLayout>
</template>
