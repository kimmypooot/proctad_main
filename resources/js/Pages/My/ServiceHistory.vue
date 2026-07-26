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

const confirmedCount = computed(() => props.records.filter((r) => r.attended).length);
const latestRecord = computed(() => props.records[0] ?? null);
const pendingEvaluations = computed(() => props.records.filter((r) => r.needs_evaluation).length);

/* --- Filters (client-side; the list is scoped to the logged-in member) --- */
const search = ref('');
const typeFilter = ref('');
const statusFilter = ref('');

const typeOptions = computed(() => {
    const seen = new Set();
    props.records.forEach((r) => r.exam_type && seen.add(r.exam_type));
    return [...seen].map((value) => ({ value, label: value }));
});

const statusOptions = computed(() => {
    const seen = new Map();
    props.records.forEach((r) => r.status_label && seen.set(r.status_label, r.status_label));
    return [...seen.values()].map((label) => ({ value: label, label }));
});

const filteredRecords = computed(() => props.records.filter((r) => {
    if (typeFilter.value && r.exam_type !== typeFilter.value) return false;
    if (statusFilter.value && r.status_label !== statusFilter.value) return false;
    if (search.value) {
        const needle = search.value.toLowerCase();
        const haystack = `${r.exam_title ?? ''} ${r.venue ?? ''}`.toLowerCase();
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
    <Head title="Service History" />

    <DashboardLayout>
        <DashboardPageHeader
            title="My Service History"
            subtitle="Your examination assignments, attendance, and performance ratings."
        >
            <template v-if="hasRecord && records.length" #actions>
                <BaseButton href="/my/service-history/print" external target="_blank" variant="outline" size="sm" icon="newspaper">
                    Print
                </BaseButton>
                <BaseButton href="/my/service-history/export" external variant="outline" size="sm" icon="arrow-down-tray">
                    Export
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="clock"
                title="No PROCTAD record linked to your account"
                description="Your service history becomes available once your Field Office registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="records.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Assignments" :value="records.length" icon="clipboard-check" />
                <StatCard compact label="Confirmed Attendance" :value="confirmedCount" icon="check-badge" accent="emerald" />
                <StatCard compact label="Most Recent" :value="latestRecord?.exam_title ?? '—'" icon="clock" />
            </div>

            <!-- Surfaced above the table as well as per row: a member with a long
                 history would otherwise have to scan for the flag to notice. -->
            <div
                v-if="pendingEvaluations"
                class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
            >
                <div class="flex items-start gap-2.5">
                    <AppIcon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">
                            {{ pendingEvaluations }} examination{{ pendingEvaluations === 1 ? '' : 's' }}
                            awaiting your evaluation.
                        </span>
                        Your feedback on how the examination was run is submitted once per assignment.
                    </p>
                </div>
                <BaseButton href="/evaluation" external variant="outline" size="sm">
                    Evaluate now
                </BaseButton>
            </div>

            <!-- Filters -->
            <BaseCard padding="sm" class="mt-6 grid gap-4 sm:grid-cols-3">
                <TextInput
                    v-model="search"
                    label="Search"
                    icon="magnifying-glass"
                    placeholder="Examination or field office"
                />
                <SelectInput
                    v-model="typeFilter"
                    label="Exam Type"
                    placeholder="All types"
                    :options="[{ value: '', label: 'All types' }, ...typeOptions]"
                />
                <SelectInput
                    v-model="statusFilter"
                    label="Status"
                    placeholder="All statuses"
                    :options="[{ value: '', label: 'All statuses' }, ...statusOptions]"
                />
            </BaseCard>
            <div v-if="hasActiveFilters" class="mt-2 flex items-center justify-between text-sm text-slate-500">
                <span>{{ filteredRecords.length }} of {{ records.length }} assignment(s)</span>
                <BaseButton variant="link" size="sm" @click="resetFilters">Clear filters</BaseButton>
            </div>

            <BaseTable
                v-if="filteredRecords.length"
                class="mt-6"
                :columns="[
                    { label: 'Examination' },
                    { label: 'Date' },
                    { label: 'Role Performed', class: 'hidden sm:table-cell' },
                    { label: 'Attendance', class: 'hidden md:table-cell' },
                    { label: 'Rating' },
                    { label: 'Actions', class: 'w-10', align: 'center' },
                ]"
            >
                        <tr v-for="record in filteredRecords" :key="record.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ record.exam_title }}</p>
                                <p class="text-xs text-slate-500">{{ record.exam_type }}</p>
                                <p class="text-xs text-slate-400 sm:hidden">{{ record.role_label }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ record.exam_date }}</td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">
                                {{ record.role_label }}
                                <span v-if="record.service_note" class="mt-0.5 block text-xs text-slate-400">
                                    {{ record.service_note }}
                                </span>
                            </td>
                            <td class="hidden px-3 py-2 md:table-cell">
                                <span v-if="record.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                    <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed
                                </span>
                                <!--
                                    A recorded absence is stated, not left as a
                                    blank that reads like a missing scan — this
                                    is the member's own record of their service.
                                -->
                                <span v-else-if="record.attendance_outcome === 'Absent'" class="text-accent-700">Absent</span>
                                <span v-else class="text-slate-400">Not confirmed</span>
                            </td>
                            <td class="px-3 py-2">
                                <BaseBadge v-if="record.rating_label" :variant="record.rating_variant">
                                    {{ record.rating_label }}
                                </BaseBadge>
                                <span v-else class="text-slate-400">—</span>
                                <!-- The evaluation is theirs to submit, so this is a
                                     prompt rather than a status: link straight to the
                                     form instead of only marking it outstanding. -->
                                <a
                                    v-if="record.needs_evaluation"
                                    href="/evaluation"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-amber-700 hover:underline"
                                >
                                    <AppIcon name="exclamation-triangle" class="h-3.5 w-3.5 shrink-0" />
                                    Evaluation needed
                                </a>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <IconButton icon="eye" label="View details" @click="viewRecord(record)" />
                            </td>
                        </tr>
            </BaseTable>
            <div v-else class="mt-6">
                <EmptyState
                    icon="clock"
                    title="No assignments match your filters"
                    description="Try adjusting or clearing the filters above."
                />
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="clock"
                title="No service records yet"
                description="Your examination assignments will appear here once your Field Office deploys you."
            />
        </div>

        <!-- View assignment modal -->
        <BaseModal :show="!!viewing" title="Assignment Details" max-width="lg" @close="closeViewer">
            <div v-if="viewing" class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ viewing.exam_type }}</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ viewing.exam_title }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ viewing.exam_date }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Role Performed</p>
                        <p class="mt-0.5 text-sm text-slate-800">{{ viewing.role_label }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Assignment Status</p>
                        <p class="mt-0.5">
                            <BaseBadge :variant="viewing.status_variant">{{ viewing.status_label }}</BaseBadge>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Venue</p>
                        <p class="mt-0.5 text-sm text-slate-800">{{ viewing.venue ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Room</p>
                        <p v-if="viewing.room" class="mt-0.5 text-sm text-slate-800">{{ viewing.room }}</p>
                        <!-- Distinguishes "deliberately withheld" from "no room recorded",
                             so a member doesn't read it as missing data and call the office. -->
                        <p v-else-if="viewing.room_withheld" class="mt-0.5 text-sm leading-snug text-slate-400">
                            Given by the secretariat when you report on exam day.
                        </p>
                        <p v-else class="mt-0.5 text-sm text-slate-800">—</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Attendance</p>
                        <p class="mt-0.5 text-sm text-slate-800">
                            <span v-if="viewing.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed
                            </span>
                            <span v-else-if="viewing.attendance_outcome === 'Absent'" class="text-accent-700">Absent</span>
                            <span v-else class="text-slate-400">Not confirmed</span>
                        </p>
                        <p v-if="viewing.attendance_confirmed_at" class="text-xs text-slate-400">{{ viewing.attendance_confirmed_at }}</p>
                        <p v-if="viewing.service_note" class="mt-1 text-xs text-slate-500">{{ viewing.service_note }}</p>
                        <p v-if="viewing.confirmed_by" class="text-xs text-slate-400">by {{ viewing.confirmed_by }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Performance Rating</p>
                        <p class="mt-0.5">
                            <BaseBadge v-if="viewing.rating_label" :variant="viewing.rating_variant">{{ viewing.rating_label }}</BaseBadge>
                            <span v-else class="text-slate-400">—</span>
                        </p>
                        <p v-if="viewing.rating_average" class="text-xs text-slate-400">
                            Average {{ viewing.rating_average }} from {{ viewing.rating_count }} rating(s)
                        </p>
                    </div>
                </div>

                <div v-if="viewing.decline_reason">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Decline Reason</p>
                    <p class="mt-0.5 text-sm text-accent-600">{{ viewing.decline_reason }}</p>
                </div>
                <div v-if="viewing.remarks">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Remarks</p>
                    <p class="mt-0.5 text-sm text-slate-800">{{ viewing.remarks }}</p>
                </div>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="closeViewer">Close</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
