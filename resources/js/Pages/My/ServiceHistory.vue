<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    records: { type: Array, required: true },
});

const confirmedCount = computed(() => props.records.filter((r) => r.attended).length);
const latestRecord = computed(() => props.records[0] ?? null);
</script>

<template>
    <Head title="Service History" />

    <DashboardLayout>
        <DashboardPageHeader
            title="My Service History"
            subtitle="Your examination assignments, attendance, and performance ratings."
        >
            <template v-if="hasRecord && records.length" #actions>
                <BaseButton href="/my/service-history/print" external target="_blank" variant="outline" size="sm">
                    <AppIcon name="newspaper" class="h-4 w-4" />
                    Print
                </BaseButton>
                <BaseButton href="/my/service-history/export" external variant="outline" size="sm">
                    <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                    Export
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="clock"
                title="No PROCTAD record linked to your account"
                description="Your service history becomes available once your Testing Center registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="records.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Assignments" :value="records.length" icon="clipboard-check" />
                <StatCard compact label="Confirmed Attendance" :value="confirmedCount" icon="check-badge" />
                <StatCard compact label="Most Recent" :value="latestRecord?.exam_title ?? '—'" icon="clock" />
            </div>

            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Examination</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="hidden px-3 py-2 sm:table-cell">Role Performed</th>
                            <th class="hidden px-3 py-2 md:table-cell">Attendance</th>
                            <th class="px-3 py-2">Rating</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="record in records" :key="record.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ record.exam_title }}</p>
                                <p class="text-xs text-slate-500">{{ record.exam_type }}</p>
                                <p class="text-xs text-slate-400 sm:hidden">{{ record.role_label }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ record.exam_date }}</td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ record.role_label }}</td>
                            <td class="hidden px-3 py-2 md:table-cell">
                                <span v-if="record.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                    <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed
                                </span>
                                <span v-else class="text-slate-400">Not confirmed</span>
                            </td>
                            <td class="px-3 py-2">
                                <BaseBadge v-if="record.rating_label" :variant="record.rating_variant">
                                    {{ record.rating_label }}
                                </BaseBadge>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="clock"
                title="No service records yet"
                description="Your examination assignments will appear here once your Testing Center deploys you."
            />
        </div>
    </DashboardLayout>
</template>
