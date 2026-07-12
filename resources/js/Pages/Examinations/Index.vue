<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

/* --- Filter: All / Upcoming --- */
const filter = ref('all');
const filteredExaminations = computed(() => {
    if (filter.value === 'upcoming') return props.examinations.filter((e) => e.upcoming);
    return props.examinations;
});

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
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Total Examinations" :value="stats.total" icon="calendar" />
            <StatCard label="Upcoming" :value="stats.upcoming" icon="clock" hint="Scheduled for a future date" />
            <StatCard label="Fully Staffed" :value="stats.fully_staffed" icon="check-circle" hint="Every assignment confirmed" />
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
        </div>

        <div v-if="filteredExaminations.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Examination</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Venues / Rooms</th>
                        <th class="hidden px-3 py-2 md:table-cell">Staffing</th>
                        <th class="w-16 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="exam in filteredExaminations" :key="exam.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2">
                            <Link :href="`/examinations/${exam.id}`" class="font-medium text-slate-900 hover:underline">
                                {{ exam.title }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ exam.type }}</p>
                            <BaseBadge v-if="exam.upcoming" variant="brand" class="mt-1">Upcoming</BaseBadge>
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
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-4">
            <EmptyState
                icon="calendar"
                :title="filter === 'upcoming' ? 'No upcoming examinations' : 'No examinations yet'"
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
