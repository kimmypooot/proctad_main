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
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    signatories: { type: Array, required: true },
    fieldOffices: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    position: '',
    field_office_id: '',
    active: true,
});

const scopeOptions = [
    { value: 'region', label: 'Region-wide (default for all Testing Centers)' },
    ...props.fieldOffices.map((fo) => ({ value: fo.id, label: fo.name })),
];

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    // FO admins can only assign their single office; preselect it.
    form.field_office_id = props.fieldOffices.length === 1 ? props.fieldOffices[0].id : 'region';
    showForm.value = true;
};

const openEdit = (signatory) => {
    editing.value = signatory;
    form.clearErrors();
    form.name = signatory.name;
    form.position = signatory.position;
    form.field_office_id = signatory.field_office_id ?? 'region';
    form.active = signatory.active;
    showForm.value = true;
};

const submit = () => {
    const transform = (data) => ({
        ...data,
        field_office_id: data.field_office_id === 'region' ? null : data.field_office_id,
    });

    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.transform(transform).put(`/signatories/${editing.value.id}`, options);
    } else {
        form.transform(transform).post('/signatories', options);
    }
};

const destroyForm = useForm({});
const confirmDelete = () => {
    destroyForm.delete(`/signatories/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head title="Signatories" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Signatories"
            subtitle="Authorized signatories for PROCTAD IDs and certificates. Changes never affect previously issued documents."
        >
            <template v-if="can.create" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    Add Signatory
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="signatories.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Position</th>
                        <th class="hidden px-3 py-2 md:table-cell">Scope</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="signatory in signatories" :key="signatory.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">
                            {{ signatory.name }}
                            <p class="text-xs font-normal text-slate-400 sm:hidden">{{ signatory.position }}</p>
                        </td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ signatory.position }}</td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <BaseBadge :variant="signatory.field_office ? 'neutral' : 'brand'">
                                {{ signatory.field_office?.name ?? 'Region-wide' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="signatory.active ? 'success' : 'neutral'">
                                {{ signatory.active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="signatory.can_manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(signatory)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = signatory" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="identification"
                title="No signatories yet"
                description="Add the officials whose names appear on PROCTAD IDs and certificates."
            />
        </div>

        <!-- Create / edit modal -->
        <BaseModal :show="showForm" :title="editing ? 'Edit Signatory' : 'Add Signatory'" @close="showForm = false">
            <form id="signatory-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="Full Name" required :error="form.errors.name" placeholder="e.g. Atty. Juana D. Reyes" />
                <TextInput v-model="form.position" label="Position" required :error="form.errors.position" placeholder="e.g. Director IV" />
                <SelectInput
                    v-model="form.field_office_id"
                    label="Scope"
                    required
                    :options="scopeOptions"
                    :error="form.errors.field_office_id"
                />
                <CheckboxInput v-model="form.active">Active (used on newly issued IDs and certificates)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="signatory-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Signatory' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove signatory" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ deleting?.name }}</strong> from the signatory list?
                Previously issued IDs and certificates are not affected.
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
