<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    summary: { type: Object, required: true },
    byFieldOffice: { type: Array, required: true },
    byGender: { type: Array, required: true },
    byExamType: { type: Array, required: true },
    byYear: { type: Array, required: true },
    trainingStats: { type: Object, required: true },
    venueReadiness: { type: Array, required: true },
});

const form = reactive({
    field_office_id: props.filters.field_office_id ?? '',
    year: props.filters.year ?? '',
    exam_type_id: props.filters.exam_type_id ?? '',
    sex: props.filters.sex ?? '',
});

const fieldOfficeOptions = computed(() => props.filterOptions.fieldOffices.map((fo) => ({ value: fo.id, label: fo.name })));
const yearOptions = computed(() => props.filterOptions.years.map((y) => ({ value: y, label: String(y) })));
const examTypeOptions = computed(() => props.filterOptions.examTypes.map((t) => ({ value: t.id, label: t.name })));
const sexOptions = [{ value: 'male', label: 'Male' }, { value: 'female', label: 'Female' }];

const applyFilters = () => router.get('/reports', { ...form }, {
    preserveState: true,
    replace: true,
    only: ['filters', 'summary', 'byFieldOffice', 'byGender', 'byExamType', 'byYear', 'trainingStats', 'venueReadiness'],
});
const clearFilters = () => {
    form.field_office_id = '';
    form.year = '';
    form.exam_type_id = '';
    form.sex = '';
    applyFilters();
};

const queryString = computed(() => {
    const params = new URLSearchParams();
    if (form.field_office_id) params.set('field_office_id', form.field_office_id);
    if (form.year) params.set('year', form.year);
    if (form.exam_type_id) params.set('exam_type_id', form.exam_type_id);
    if (form.sex) params.set('sex', form.sex);
    return params.toString();
});

const exportUrl = (path) => `${path}${queryString.value ? `?${queryString.value}` : ''}`;

const attendanceRate = (total, attended) => (total > 0 ? Math.round((attended / total) * 100) : null);
</script>

<template>
    <Head title="Reports & Statistics" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Reports & Statistics"
            subtitle="Region-wide and Testing Center statistics — filter by Testing Center, year, exam type, and gender."
        />

        <!-- Filters -->
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <SelectInput
                    v-if="filterOptions.canPickFieldOffice"
                    v-model="form.field_office_id"
                    label="Testing Center"
                    optional
                    placeholder="All Testing Centers"
                    :options="fieldOfficeOptions"
                />
                <SelectInput v-model="form.year" label="Year" optional placeholder="All Years" :options="yearOptions" />
                <SelectInput v-model="form.exam_type_id" label="Exam Type" optional placeholder="All Exam Types" :options="examTypeOptions" />
                <SelectInput v-model="form.sex" label="Gender" optional placeholder="All Genders" :options="sexOptions" />
                <div class="flex items-end gap-2">
                    <BaseButton variant="primary" size="sm" @click="applyFilters">Apply</BaseButton>
                    <BaseButton variant="outline" size="sm" @click="clearFilters">Clear</BaseButton>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Active Members" :value="summary.active_members" />
            <StatCard label="Service Records" :value="summary.service_records" />
            <StatCard label="Attendance Confirmed">
                {{ summary.attended }}
                <span v-if="summary.service_records" class="text-sm font-normal text-slate-400">
                    ({{ attendanceRate(summary.service_records, summary.attended) }}%)
                </span>
            </StatCard>
        </div>

        <!-- Exports -->
        <div class="mt-6 flex flex-wrap gap-3">
            <BaseButton variant="outline" size="sm" external :href="exportUrl('/reports/export/members')">
                Export Members (Excel)
            </BaseButton>
            <BaseButton variant="outline" size="sm" external :href="exportUrl('/reports/export/service-records')">
                Export Service Records (Excel)
            </BaseButton>
            <BaseButton variant="outline" size="sm" external :href="exportUrl('/reports/export/training-attendance')">
                Export Training Attendance (Excel)
            </BaseButton>
        </div>

        <!-- Breakdowns -->
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-900">By Testing Center</h2>
                <table v-if="byFieldOffice.length" class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byFieldOffice" :key="row.label" class="transition-colors hover:bg-brand-50/40">
                            <td class="max-w-[10rem] truncate py-2 text-slate-700" :title="row.label">{{ row.label }}</td>
                            <td class="whitespace-nowrap py-2 text-right text-slate-500">{{ row.attended }} / {{ row.total }} attended</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-3 text-sm text-slate-400">No data for the selected filters.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-900">By Gender</h2>
                <table v-if="byGender.length" class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byGender" :key="row.label" class="transition-colors hover:bg-brand-50/40">
                            <td class="py-2 text-slate-700">{{ row.label }}</td>
                            <td class="py-2 text-right text-slate-500">{{ row.attended }} / {{ row.total }} attended</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-3 text-sm text-slate-400">No data for the selected filters.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-900">By Exam Type</h2>
                <table v-if="byExamType.length" class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byExamType" :key="row.label" class="transition-colors hover:bg-brand-50/40">
                            <td class="py-2 text-slate-700">{{ row.label }}</td>
                            <td class="py-2 text-right text-slate-500">{{ row.attended }} / {{ row.total }} attended</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-3 text-sm text-slate-400">No data for the selected filters.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-base font-semibold text-slate-900">By Year</h2>
                <table v-if="byYear.length" class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byYear" :key="row.label" class="transition-colors hover:bg-brand-50/40">
                            <td class="py-2 text-slate-700">{{ row.label }}</td>
                            <td class="py-2 text-right text-slate-500">{{ row.attended }} / {{ row.total }} attended</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="mt-3 text-sm text-slate-400">No data for the selected filters.</p>
            </div>
        </div>

        <!-- Training attendance stats -->
        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-900">Training Attendance</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <StatCard label="Total Participants" :value="trainingStats.total_participants" :bordered="false" />
                <StatCard label="Attendance Confirmed" :value="trainingStats.attended" :bordered="false" />
                <StatCard
                    label="Attendance Rate"
                    :value="trainingStats.attendance_rate !== null ? `${trainingStats.attendance_rate}%` : '—'"
                    :bordered="false"
                />
            </div>
        </div>

        <!-- Venue readiness -->
        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-900">Venue Staffing Readiness</h2>
            <p class="mt-1 text-xs text-slate-500">Rooms with at least one staff member assigned, vs total rooms, per venue.</p>
            <div v-if="venueReadiness.length" class="overflow-x-auto">
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">School</th>
                        <th class="hidden py-2 pr-3 sm:table-cell">Examination</th>
                        <th class="hidden py-2 pr-3 md:table-cell">Date</th>
                        <th class="py-2 pr-3">Rooms Staffed</th>
                        <th class="py-2">Readiness</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in venueReadiness" :key="`${row.school}-${row.examination}`" class="transition-colors hover:bg-brand-50/40">
                        <td class="max-w-[10rem] truncate py-2 pr-3 text-slate-700" :title="row.school">
                            {{ row.school }}
                            <p class="truncate text-xs text-slate-400 sm:hidden">{{ row.examination }}</p>
                        </td>
                        <td class="hidden max-w-[12rem] truncate py-2 pr-3 text-slate-700 sm:table-cell" :title="row.examination">{{ row.examination }}</td>
                        <td class="hidden whitespace-nowrap py-2 pr-3 text-slate-500 md:table-cell">{{ row.exam_date }}</td>
                        <td class="whitespace-nowrap py-2 pr-3 text-slate-500">{{ row.staffed_rooms }} / {{ row.total_rooms }}</td>
                        <td class="py-2">
                            <BaseBadge v-if="row.readiness_percent !== null" :variant="row.readiness_percent >= 100 ? 'success' : row.readiness_percent >= 50 ? 'warning' : 'neutral'">
                                {{ row.readiness_percent }}%
                            </BaseBadge>
                            <span v-else class="text-slate-400">No rooms</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <p v-else class="mt-3 text-sm text-slate-400">No venues for the selected filters.</p>
        </div>
    </DashboardLayout>
</template>
