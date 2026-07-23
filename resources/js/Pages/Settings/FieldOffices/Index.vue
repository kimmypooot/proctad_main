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
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    fieldOffices: { type: Array, required: true },
    stats: { type: Object, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({ name: '', code: '', address: '', is_active: true });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    showForm.value = true;
};

const openEdit = (office) => {
    editing.value = office;
    form.clearErrors();
    form.name = office.name;
    form.code = office.code;
    form.address = office.address ?? '';
    form.is_active = office.is_active;
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    if (editing.value) {
        form.put(`/field-offices/${editing.value.id}`, options);
    } else {
        form.post('/field-offices', options);
    }
};
</script>

<template>
    <Head title="Testing Centers" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Testing Centers"
            subtitle="Testing centers administered by CSC Regional Office VIII."
        >
            <template v-if="can.manage" #actions>
                <BaseButton variant="primary" size="sm" icon="plus" @click="openCreate">
                    Add Testing Center
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Total Testing Centers" :value="stats.total" icon="building-office" />
            <StatCard label="Active" :value="stats.active" icon="check-circle" hint="Selectable for new records" />
            <StatCard label="Hidden" :value="stats.hidden" icon="x-circle" />
        </div>

        <div v-if="fieldOffices.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Code</th>
                        <th class="hidden px-3 py-2 md:table-cell">Address</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Users</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-10 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="office in fieldOffices" :key="office.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">{{ office.name }}</td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold text-brand-700">{{ office.code }}</td>
                        <td class="hidden max-w-[16rem] truncate px-3 py-2 text-slate-600 md:table-cell" :title="office.address ?? ''">{{ office.address ?? '—' }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 sm:table-cell">{{ office.users_count }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="office.is_active ? 'success' : 'neutral'">
                                {{ office.is_active ? 'Active' : 'Hidden' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <IconButton v-if="can.manage" icon="pencil" label="Edit" @click="openEdit(office)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="building-office"
                title="No field offices yet"
                description="Add the testing centers PROCTAD operates under."
            />
        </div>

        <!-- Create / edit modal -->
        <BaseModal :show="showForm" :title="editing ? 'Edit Testing Center' : 'Add Testing Center'" @close="showForm = false">
            <form id="field-office-form" class="space-y-4" novalidate @submit.prevent="submit">
                <TextInput v-model="form.name" label="Name" required :error="form.errors.name" placeholder="e.g. Leyte Testing Center" />
                <TextInput v-model="form.code" label="Code" required :error="form.errors.code" placeholder="e.g. LEY" />
                <TextInput v-model="form.address" label="Address" :error="form.errors.address" placeholder="e.g. Palo, Leyte" />
                <CheckboxInput v-model="form.is_active">
                    Active (selectable when assigning members, users, schools, and signatories)
                </CheckboxInput>
                <p v-if="editing && !form.is_active" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Hiding this testing center removes it from selection when creating new records. Existing members,
                    users, schools, and signatories already assigned here are unaffected.
                </p>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showForm = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="field-office-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save Changes' : 'Add Testing Center' }}
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
