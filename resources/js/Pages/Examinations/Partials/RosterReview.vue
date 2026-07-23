<script setup>
import { computed } from 'vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    examination: { type: Object, required: true },
    assignments: { type: Array, required: true },
    can: { type: Object, required: true },
});

const groups = computed(() => {
    const byGroup = new Map();

    for (const assignment of props.assignments) {
        const key = assignment.role_group_label;
        if (!byGroup.has(key)) {
            byGroup.set(key, { label: key, total: 0, confirmed: 0, pending: 0, declined: 0, unassignedVenue: 0 });
        }
        const bucket = byGroup.get(key);
        bucket.total += 1;
        if (assignment.status === 'confirmed') bucket.confirmed += 1;
        else if (assignment.status === 'declined') bucket.declined += 1;
        else bucket.pending += 1;
        if (!assignment.venue) bucket.unassignedVenue += 1;
    }

    return [...byGroup.values()];
});

const totals = computed(() => props.assignments.reduce(
    (acc, a) => {
        acc.total += 1;
        if (a.status === 'confirmed') acc.confirmed += 1;
        else if (a.status === 'declined') acc.declined += 1;
        else acc.pending += 1;
        if (!a.attended) acc.notYetAttended += 1;
        return acc;
    },
    { total: 0, confirmed: 0, pending: 0, declined: 0, notYetAttended: 0 },
));
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-base font-semibold text-slate-900">Step 4 · Review Roster</h2>
        <p class="mt-1 text-sm text-slate-500">
            Read-only summary of who is assigned to this examination and their confirmation status.
        </p>

        <div v-if="assignments.length" class="mt-5 space-y-5">
            <!-- Overall totals -->
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Assigned</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ totals.total }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-600">Confirmed</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">{{ totals.confirmed }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-600">Pending</p>
                    <p class="mt-1 text-xl font-bold text-amber-700">{{ totals.pending }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-600">Declined</p>
                    <p class="mt-1 text-xl font-bold text-rose-700">{{ totals.declined }}</p>
                </div>
            </div>

            <!-- Breakdown by role group -->
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Role Group</th>
                            <th class="px-3 py-2">Total</th>
                            <th class="px-3 py-2">Confirmed</th>
                            <th class="px-3 py-2">Pending</th>
                            <th class="px-3 py-2">Declined</th>
                            <th class="px-3 py-2">No Venue Yet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="group in groups" :key="group.label" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ group.label }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ group.total }}</td>
                            <td class="px-3 py-2">
                                <BaseBadge variant="success">{{ group.confirmed }}</BaseBadge>
                            </td>
                            <td class="px-3 py-2">
                                <BaseBadge variant="warning">{{ group.pending }}</BaseBadge>
                            </td>
                            <td class="px-3 py-2">
                                <BaseBadge :variant="group.declined ? 'accent' : 'neutral'">{{ group.declined }}</BaseBadge>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <span :class="group.unassignedVenue ? 'text-amber-600' : 'text-slate-400'">
                                    {{ group.unassignedVenue }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="totals.notYetAttended > 0" class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-600">
                {{ totals.notYetAttended }} assignment{{ totals.notYetAttended === 1 ? '' : 's' }} not yet marked present on exam day.
            </p>

            <div v-if="can.assign" class="flex justify-end">
                <BaseButton :href="`/scanner?examination_id=${examination.id}`" variant="primary" size="sm" icon="qr-code">
                    Scan Attendance
                </BaseButton>
            </div>
        </div>

        <EmptyState
            v-else
            class="mt-5"
            icon="clipboard-check"
            title="No one assigned yet"
            description="Go back to Step 2 to assign PROCTAD members before reviewing the roster."
        />
    </div>
</template>
