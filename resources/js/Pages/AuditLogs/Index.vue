<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, required: true },
});

const action = ref(props.filters.action ?? '');
const type = ref(props.filters.type ?? '');
const loading = ref(false);

watch([action, type], () => {
    router.get('/audit-logs', {
        action: action.value || undefined,
        type: type.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        only: ['logs', 'filters'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
});

const actionVariant = { created: 'success', updated: 'brand', deleted: 'warning' };

const changeSummary = (log) => {
    if (!log.changes?.new) return null;
    return Object.keys(log.changes.new).join(', ');
};
</script>

<template>
    <Head title="Audit Trail" />

    <DashboardLayout>
        <DashboardPageHeader title="Audit Trail" subtitle="Every create, update, and delete across PROCTAD records." />

        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
            <SelectInput
                v-model="action"
                label="Action"
                placeholder="All actions"
                :options="[
                    { value: '', label: 'All actions' },
                    { value: 'created', label: 'Created' },
                    { value: 'updated', label: 'Updated' },
                    { value: 'deleted', label: 'Deleted' },
                ]"
            />
            <SelectInput
                v-model="type"
                label="Record Type"
                placeholder="All types"
                :options="[{ value: '', label: 'All types' }, ...types]"
            />
        </div>

        <div v-if="loading || logs.data.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Who</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Record</th>
                        <th class="hidden px-3 py-2 md:table-cell">Fields Changed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="5" />
                    <tr v-for="log in loading ? [] : logs.data" :key="log.id" class="align-top transition-colors hover:bg-brand-50/40">
                        <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ log.created_at }}</td>
                        <td class="px-3 py-2 font-medium text-slate-800">{{ log.actor }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="actionVariant[log.action] ?? 'neutral'">{{ log.action }}</BaseBadge>
                            <p class="mt-1 whitespace-nowrap text-xs text-slate-400 sm:hidden">
                                {{ log.model }} #{{ log.record_id }}
                            </p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">
                            {{ log.model }} <span class="text-slate-400">#{{ log.record_id }}</span>
                        </td>
                        <td class="hidden max-w-xs truncate px-3 py-2 text-xs text-slate-500 md:table-cell" :title="changeSummary(log) ?? ''">
                            {{ changeSummary(log) ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="eye"
                title="No audit entries"
                description="Activity will appear here as records are created, updated, or removed."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="logs.links" />
        </div>
    </DashboardLayout>
</template>
