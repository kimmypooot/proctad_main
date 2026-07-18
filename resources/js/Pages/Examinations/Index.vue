<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    examinations: { type: Array, required: true },
    stats: { type: Object, required: true },
    examTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const nextExamination = computed(() => {
    const upcoming = props.examinations
        .filter((e) => e.status === 'upcoming')
        .sort((a, b) => new Date(a.exam_date) - new Date(b.exam_date));
    return upcoming.length ? upcoming[0] : null;
});

const daysUntil = computed(() => {
    if (!nextExamination.value) return 0;
    const diff = new Date(nextExamination.value.exam_date) - new Date();
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
});

const formattedExamDate = computed(() => {
    if (!nextExamination.value) return '';
    return new Date(nextExamination.value.exam_date).toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
});

/* --- Filter: All / Upcoming / Ongoing / Completed --- */
const filter = ref('all');
const filteredExaminations = computed(() => {
    if (filter.value === 'all') return props.examinations;
    return props.examinations.filter((e) => e.status === filter.value);
});

const statusBadge = (status) => {
    if (status === 'ongoing') return { variant: 'success', label: 'Ongoing' };
    if (status === 'upcoming') return { variant: 'brand', label: 'Upcoming' };
    return { variant: 'neutral', label: 'Completed' };
};

const staffingVariant = (exam) => {
    if (exam.staffing_ratio === null) return 'neutral';
    if (exam.staffing_ratio >= 100) return 'success';
    if (exam.staffing_ratio > 0) return 'warning';
    return 'neutral';
};

/* --- Add / edit modal --- */
const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    title: '',
    exam_type_id: '',
    exam_date: '',
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (exam) => {
    editing.value = exam;
    form.clearErrors();
    form.title = exam.title;
    form.exam_type_id = exam.exam_type_id;
    form.exam_date = exam.exam_date;
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.put(`/examinations/${editing.value.id}`, options);
    } else {
        form.post('/examinations', options);
    }
};
</script>

<template>
    <Head title="Examinations" />

    <DashboardLayout>
        <DashboardPageHeader title="Examinations" subtitle="Examination events and PROCTAD deployment.">
            <template v-if="can.manage" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    Add Examination
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- At-a-glance stats -->
        <div class="mt-6 grid gap-4 sm:grid-cols-4">
            <StatCard label="Total Examinations" :value="stats.total" icon="calendar" />
            <StatCard label="Upcoming" :value="stats.upcoming" icon="clock" hint="Scheduled for a future date" />
            <StatCard label="Ongoing" :value="stats.ongoing" icon="check-circle" hint="Examination day" />
            <StatCard label="Completed" :value="stats.completed" icon="flag" hint="Past examinations" />
        </div>

        <!-- Next Examination spotlight -->
        <div v-if="nextExamination" class="mt-6 overflow-hidden rounded-xl border border-brand-200 bg-gradient-to-br from-white to-brand-50 shadow-sm">
            <div class="p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-xs text-white">★</span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-brand-600">Next Examination</span>
                        </div>

                        <div>
                            <h2 class="text-lg font-bold text-slate-900 sm:text-xl">{{ nextExamination.title }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ nextExamination.type }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="text-base">📅</span>
                                <span>{{ formattedExamDate }}</span>
                                <span class="ml-1 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700">
                                    {{ daysUntil }} day{{ daysUntil === 1 ? '' : 's' }} away
                                </span>
                            </span>
                            <span v-if="nextExamination.venue_names.length" class="inline-flex items-center gap-1.5">
                                <span class="text-base">📍</span>
                                <span>{{ nextExamination.venue_names.join(', ') }}</span>
                            </span>
                        </div>

                        <div v-if="nextExamination.assignments_count" class="flex items-center gap-3">
                            <div class="h-2 w-36 overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="{
                                        'bg-emerald-500': (nextExamination.staffing_ratio ?? 0) >= 100,
                                        'bg-amber-500': (nextExamination.staffing_ratio ?? 0) > 0 && (nextExamination.staffing_ratio ?? 0) < 100,
                                        'bg-slate-300': (nextExamination.staffing_ratio ?? 0) === 0,
                                    }"
                                    :style="{ width: `${nextExamination.staffing_ratio ?? 0}%` }"
                                />
                            </div>
                            <span class="text-xs font-medium text-slate-500">
                                {{ nextExamination.confirmed_count }}/{{ nextExamination.assignments_count }} confirmed
                            </span>
                        </div>
                        <p v-else class="text-xs text-slate-400">No members assigned yet</p>
                    </div>

                    <Link
                        :href="`/examinations/${nextExamination.id}`"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-brand-700"
                    >
                        Manage Examination
                        <span class="text-base">→</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="mt-6 flex gap-2">
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="filter === 'all' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="filter = 'all'"
            >
                All
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="filter === 'upcoming' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="filter = 'upcoming'"
            >
                Upcoming
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="filter === 'ongoing' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="filter = 'ongoing'"
            >
                Ongoing
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                :class="filter === 'completed' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="filter = 'completed'"
            >
                Completed
            </button>
        </div>

        <div v-if="filteredExaminations.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Examination</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Venues / Rooms</th>
                        <th class="hidden px-3 py-2 md:table-cell">Staffing</th>
                        <th class="w-24 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="exam in filteredExaminations" :key="exam.id" class="transition-colors hover:bg-brand-50/40" :class="{ 'opacity-50': !exam.is_active }">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1.5">
                                <Link :href="`/examinations/${exam.id}`" class="font-medium text-slate-900 hover:underline">
                                    {{ exam.title }}
                                </Link>
                                <span v-if="!exam.is_active" class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Hidden</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ exam.type }}</p>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <BaseBadge :variant="statusBadge(exam.status).variant">{{ statusBadge(exam.status).label }}</BaseBadge>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ exam.exam_date }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 xl:table-cell">
                            <template v-if="exam.venues_count">
                                {{ exam.venues_count }} venue{{ exam.venues_count === 1 ? '' : 's' }} ·
                                {{ exam.rooms_count }} room{{ exam.rooms_count === 1 ? '' : 's' }}
                            </template>
                            <span v-else class="text-slate-400">No venues yet</span>
                        </td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <template v-if="exam.assignments_count">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="{
                                                'bg-emerald-500': staffingVariant(exam) === 'success',
                                                'bg-amber-500': staffingVariant(exam) === 'warning',
                                            }"
                                            :style="{ width: `${exam.staffing_ratio}%` }"
                                        />
                                    </div>
                                    <BaseBadge :variant="staffingVariant(exam)" class="whitespace-nowrap">
                                        {{ exam.confirmed_count }}/{{ exam.assignments_count }}
                                    </BaseBadge>
                                </div>
                            </template>
                            <span v-else class="text-slate-400">No assignments</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="inline-flex gap-1">
                                <IconButton :href="`/examinations/${exam.id}`" icon="eye" label="Manage" />
                                <IconButton v-if="can.manage" icon="pencil" label="Edit" @click="openEdit(exam)" />
                                <IconButton v-if="can.manage" :icon="exam.is_active ? 'eye-slash' : 'eye'" :label="exam.is_active ? 'Hide from dropdowns' : 'Show in dropdowns'" @click="router.patch(`/examinations/${exam.id}/toggle-visibility`)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-4">
            <EmptyState
                icon="calendar"
                :title="{
                    upcoming: 'No upcoming examinations',
                    ongoing: 'No ongoing examinations',
                    completed: 'No completed examinations',
                }[filter] ?? 'No examinations yet'"
                description="Examination events appear here once created by the Examination Services Division."
            />
        </div>

        <BaseModal :show="showForm" :title="editing ? 'Edit Examination' : 'Add Examination'" @close="showForm = false">
            <form id="exam-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.title" label="Title" required placeholder="e.g. August 2026 CSE-PPT" :error="form.errors.title" />
                <SelectInput
                    v-model="form.exam_type_id"
                    label="Exam Type"
                    required
                    placeholder="Select an exam type"
                    :options="examTypes"
                    :error="form.errors.exam_type_id"
                />
                <TextInput v-model="form.exam_date" label="Examination Date" type="date" required :error="form.errors.exam_date" />
                <p v-if="!editing" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Venues are attached after creation, in Step 1 of the setup wizard.
                </p>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="exam-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Examination' }}
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
