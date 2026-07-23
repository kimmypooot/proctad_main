<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import ViewTrainingModal from './Partials/ViewTrainingModal.vue';

const props = defineProps({
    trainings: { type: Array, required: true },
    types: { type: Array, required: true },
    exams: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const editing = ref(null);
const viewing = ref(null);

const form = useForm({
    title: '',
    type: '',
    training_date: '',
    end_date: '',
    venue: '',
    exam_id: '',
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (training) => {
    editing.value = training;
    form.clearErrors();
    form.title = training.title;
    form.type = training.type;
    form.training_date = training.training_date;
    form.end_date = '';
    form.venue = training.venue ?? '';
    form.exam_id = training.exam?.id ?? '';
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.put(`/trainings/${editing.value.id}`, options);
    } else {
        form.post('/trainings', options);
    }
};
</script>

<template>
    <Head title="Trainings" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Training Sessions"
            subtitle="Orientation, briefing, and examination-administration training for PROCTAD members."
        >
            <template v-if="can.manage" #actions>
                <BaseButton variant="primary" size="sm" icon="plus" @click="openCreate">
                    Add Training
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <!-- Mobile (below md): a card per training. Tap to view; edit stays a button. -->
        <div v-if="trainings.length" class="mt-6 space-y-3 md:hidden">
            <div
                v-for="training in trainings"
                :key="`m-${training.id}`"
                class="rounded-xl border border-slate-200 bg-white p-4"
                @click="viewing = training"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ training.title }}</p>
                        <p class="text-xs text-slate-500">{{ training.type_label }}</p>
                    </div>
                    <BaseBadge :variant="training.completed ? 'success' : 'warning'" class="shrink-0">
                        {{ training.completed ? 'Completed' : 'Scheduled' }}
                    </BaseBadge>
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
                    <div><dt class="font-medium text-slate-400">Date</dt><dd class="text-slate-600">{{ training.training_date }}</dd></div>
                    <div><dt class="font-medium text-slate-400">Participants</dt><dd class="text-slate-600">{{ training.assignments_count }}</dd></div>
                    <div v-if="training.field_office?.name" class="col-span-2"><dt class="font-medium text-slate-400">Field Office</dt><dd class="text-slate-600">{{ training.field_office.name }}</dd></div>
                    <div v-if="training.exam?.title" class="col-span-2"><dt class="font-medium text-slate-400">Connected Exam</dt><dd class="text-slate-600">{{ training.exam.title }}</dd></div>
                    <div v-if="training.venue" class="col-span-2"><dt class="font-medium text-slate-400">Venue</dt><dd class="text-slate-600">{{ training.venue }}</dd></div>
                </dl>
                <div v-if="can.manage && !training.completed" class="mt-3 flex gap-1 border-t border-slate-100 pt-3">
                    <IconButton icon="pencil" label="Edit" @click.stop="openEdit(training)" />
                </div>
            </div>
        </div>

        <div v-if="trainings.length" class="mt-6 hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Training</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                        <th class="hidden px-3 py-2 lg:table-cell">Field Office</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Connected Exam</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Venue</th>
                        <th class="hidden px-3 py-2 md:table-cell">Participants</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-10 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="training in trainings" :key="training.id" class="cursor-pointer transition-colors hover:bg-brand-50/40" @click="viewing = training">
                        <td class="px-3 py-2">
                            <span class="font-medium text-slate-900 hover:underline">{{ training.title }}</span>
                            <p class="text-xs text-slate-400 sm:hidden">{{ training.type_label }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ training.type_label }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 lg:table-cell">{{ training.field_office?.name ?? '—' }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 xl:table-cell">{{ training.exam?.title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ training.training_date }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 xl:table-cell">{{ training.venue ?? '—' }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 md:table-cell">{{ training.assignments_count }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="training.completed ? 'success' : 'warning'">
                                {{ training.completed ? 'Completed' : 'Scheduled' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <IconButton v-if="can.manage && !training.completed" icon="pencil" label="Edit" @click.stop="openEdit(training)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="academic-cap"
                title="No training sessions yet"
                description="Scheduled trainings and briefings appear here."
            />
        </div>

        <ViewTrainingModal :show="!!viewing" :training-id="viewing?.id" @close="viewing = null" @saved="viewing = null" />

        <BaseModal :show="showForm" :title="editing ? 'Edit Training' : 'Add Training'" @close="showForm = false">
            <form id="training-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.title" label="Title" required placeholder="e.g. PROCTAD Orientation" :error="form.errors.title" />
                <SelectInput v-model="form.type" label="Type" required :options="types" :error="form.errors.type" />
                <TextInput v-model="form.training_date" label="Training Date" type="date" required :error="form.errors.training_date" />
                <TextInput v-model="form.end_date" label="End Date" type="date" optional :error="form.errors.end_date" />
                <TextInput v-model="form.venue" label="Venue" optional :error="form.errors.venue" />
                <SelectInput v-model="form.exam_id" label="Connected Exam" required placeholder="Select an exam" :options="exams" :error="form.errors.exam_id" />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="training-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Training' }}
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
