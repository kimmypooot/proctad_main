<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    records: { type: Array, required: true },
});

const completedCount = computed(() => props.records.filter((r) => r.completed).length);
const confirmedCount = computed(() => props.records.filter((r) => r.attended).length);
</script>

<template>
    <Head title="My Trainings" />

    <DashboardLayout>
        <DashboardPageHeader title="My Trainings" subtitle="Training sessions and briefings you've been enrolled in." />

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="academic-cap"
                title="No PROCTAD record linked to your account"
                description="Your training records become available once your Testing Center registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="records.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Trainings" :value="records.length" icon="academic-cap" />
                <StatCard compact label="Completed" :value="completedCount" icon="check-badge" />
                <StatCard compact label="Attendance Confirmed" :value="confirmedCount" icon="check-circle" />
            </div>

            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Training</th>
                            <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="hidden px-3 py-2 sm:table-cell">Attendance</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="record in records" :key="record.id" class="transition-colors hover:bg-brand-50/40">
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
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="academic-cap"
                title="No training records yet"
                description="Trainings you're enrolled in will appear here."
            />
        </div>
    </DashboardLayout>
</template>
