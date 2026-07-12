<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    examTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({ name: '', is_active: true });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (type) => {
    editing.value = type;
    form.clearErrors();
    form.name = type.name;
    form.is_active = type.is_active;
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.put(`/exam-types/${editing.value.id}`, options);
    } else {
        form.post('/exam-types', options);
    }
};

const destroyForm = useForm({});
const confirmDelete = () => destroyForm.delete(`/exam-types/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});
</script>

<template>
    <Head title="Examination Types" />

    <DashboardLayout>
        <DashboardPageHeader title="Examination Types" subtitle="e.g. Career Service Examination, Fire Officer Examination.">
            <template v-if="can.manage" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    Add Type
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="examTypes.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Examinations</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="type in examTypes" :key="type.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">{{ type.name }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ type.examinations_count }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="type.is_active ? 'success' : 'neutral'">
                                {{ type.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="can.manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(type)" />
                                <IconButton
                                    v-if="type.examinations_count === 0"
                                    icon="trash"
                                    label="Remove"
                                    variant="danger"
                                    @click="deleting = type"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="clipboard-check"
                title="No examination types yet"
                description="Add the types of examinations PROCTAD administers."
            />
        </div>

        <!-- Create / edit modal -->
        <BaseModal :show="showForm" :title="editing ? 'Edit Examination Type' : 'Add Examination Type'" @close="showForm = false">
            <form id="exam-type-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="Name" required :error="form.errors.name" placeholder="e.g. Career Service Examination" />
                <CheckboxInput v-model="form.is_active">Active</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="exam-type-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Type' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove examination type" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.name }}</strong>?
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="deleting = null">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="destroyForm.processing" @click="confirmDelete">
                    Remove
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
