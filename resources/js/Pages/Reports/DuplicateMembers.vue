<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    emailGroups: { type: Array, required: true },
    nameGroups: { type: Array, required: true },
});
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
                <div v-for="group in emailGroups" :key="group.key" class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                        {{ group.label }}
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">PROCTAD ID</th>
                                <th class="px-3 py-2">Name</th>
                                <th class="hidden px-3 py-2 sm:table-cell">Testing Center</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="hidden px-3 py-2 md:table-cell">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="member in group.members" :key="member.id" class="transition-colors hover:bg-brand-50/40">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-brand-700">{{ member.proctad_id }}</td>
                                <td class="px-3 py-2">
                                    <Link :href="`/members/${member.id}`" class="font-medium text-slate-900 hover:underline">
                                        {{ member.name }}
                                    </Link>
                                </td>
                                <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ member.field_office }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ member.status_label }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2 text-slate-500 md:table-cell">{{ member.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-slate-400">No duplicate emails found.</p>
        </section>

        <section class="mt-10">
            <h2 class="text-lg font-semibold text-slate-900">
                Duplicate Name Groups <span class="font-normal text-slate-400">({{ nameGroups.length }})</span>
            </h2>

            <div v-if="nameGroups.length" class="mt-3 space-y-6">
                <div v-for="group in nameGroups" :key="group.key" class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                        {{ group.label }}
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">PROCTAD ID</th>
                                <th class="px-3 py-2">Email</th>
                                <th class="hidden px-3 py-2 sm:table-cell">Testing Center</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="hidden px-3 py-2 md:table-cell">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="member in group.members" :key="member.id" class="transition-colors hover:bg-brand-50/40">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-brand-700">
                                    <Link :href="`/members/${member.id}`" class="hover:underline">{{ member.proctad_id }}</Link>
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ member.email }}</td>
                                <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ member.field_office }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ member.status_label }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2 text-slate-500 md:table-cell">{{ member.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
