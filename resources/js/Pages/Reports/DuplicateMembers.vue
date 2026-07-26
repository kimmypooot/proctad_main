<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    emailGroups: { type: Array, required: true },
    nameGroups: { type: Array, required: true },
});

const emailGroupColumns = [
    { label: 'PROCTAD ID' },
    { label: 'Name' },
    { label: 'Field Office', class: 'hidden sm:table-cell' },
    { label: 'Status' },
    { label: 'Registered', class: 'hidden md:table-cell' },
];

const nameGroupColumns = [
    { label: 'PROCTAD ID' },
    { label: 'Email' },
    { label: 'Field Office', class: 'hidden sm:table-cell' },
    { label: 'Status' },
    { label: 'Registered', class: 'hidden md:table-cell' },
];
</script>

<template>
    <Head title="Duplicate Members Report" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Duplicate Members Report"
            subtitle="Read-only report — no records are merged or deleted automatically. Review each group manually."
        />

        <section class="mt-8">
            <h2 class="text-lg font-semibold text-slate-900">
                Duplicate Email Groups <span class="font-normal text-slate-400">({{ emailGroups.length }})</span>
            </h2>

            <div v-if="emailGroups.length" class="mt-3 space-y-6">
                <BaseCard v-for="group in emailGroups" :key="group.key" padding="none" class="overflow-x-auto">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                        {{ group.label }}
                    </div>
                    <BaseTable bare :columns="emailGroupColumns">
                        <tr v-for="member in group.members" :key="member.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-brand-700">{{ member.proctad_id }}</td>
                            <td class="px-3 py-2">
                                <Link href="/members" class="font-medium text-slate-900 hover:underline">
                                    {{ member.name }}
                                </Link>
                            </td>
                            <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ member.field_office }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ member.status_label }}</td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-500 md:table-cell">{{ member.created_at }}</td>
                        </tr>
                    </BaseTable>
                </BaseCard>
            </div>
            <p v-else class="mt-3 text-sm text-slate-400">No duplicate emails found.</p>
        </section>

        <section class="mt-10">
            <h2 class="text-lg font-semibold text-slate-900">
                Duplicate Name Groups <span class="font-normal text-slate-400">({{ nameGroups.length }})</span>
            </h2>

            <div v-if="nameGroups.length" class="mt-3 space-y-6">
                <BaseCard v-for="group in nameGroups" :key="group.key" padding="none" class="overflow-x-auto">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                        {{ group.label }}
                    </div>
                    <BaseTable bare :columns="nameGroupColumns">
                        <tr v-for="member in group.members" :key="member.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-brand-700">
                                <Link href="/members" class="hover:underline">{{ member.proctad_id }}</Link>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ member.email }}</td>
                            <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ member.field_office }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ member.status_label }}</td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-500 md:table-cell">{{ member.created_at }}</td>
                        </tr>
                    </BaseTable>
                </BaseCard>
            </div>
            <p v-else class="mt-3 text-sm text-slate-400">No duplicate names found.</p>
        </section>

        <div v-if="!emailGroups.length && !nameGroups.length" class="mt-6">
            <EmptyState
                icon="check-badge"
                title="No duplicates found"
                description="The member registry currently has no colliding emails or names."
            />
        </div>
    </DashboardLayout>
</template>
