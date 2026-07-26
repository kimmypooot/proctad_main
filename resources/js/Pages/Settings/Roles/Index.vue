<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseTable from '@/Components/BaseTable.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import IconButton from '@/Components/IconButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    roles: { type: Array, required: true },
});

const editing = ref(null);
const editForm = useForm({ role: '', label: '' });

const openEdit = (role) => {
    editing.value = role;
    editForm.clearErrors();
    editForm.role = role.value;
    editForm.label = role.label;
};

const submitEdit = () => editForm.put('/roles', {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});
</script>

<template>
    <Head title="Roles" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Roles"
            subtitle="Rename the built-in roles to match the terms your office uses."
        />

        <BaseCard padding="sm" class="mt-6">
            <p class="text-sm leading-relaxed text-slate-600">
                Renaming changes what a role is <strong>called</strong>, never what it can
                <strong>do</strong>. The underlying role each account holds stays the same, so no
                permission, jurisdiction or approval rule is affected. To change what a role is
                allowed to do, use <strong>Role Permissions</strong>.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Roles cannot be added or deleted here. Several are named directly by the rules that
                route certificate approvals and compose the Regional Examination Committee, and each
                has its own navigation — so adding or removing one is a change to the system itself.
            </p>
        </BaseCard>

        <BaseTable
            class="mt-6"
            :columns="[
                { label: 'Role' },
                { label: 'Reach', class: 'hidden md:table-cell' },
                { label: 'Accounts', align: 'center' },
                { label: 'Actions', align: 'center' },
            ]"
        >
            <tr v-for="role in roles" :key="role.value" class="transition-colors hover:bg-brand-50/40">
                <td class="px-3 py-2">
                    <!-- The current name is the only name shown. Renaming is the single
                         way to change it, so nothing here refers back to a built-in
                         original. -->
                    <p class="font-medium text-slate-900">{{ role.label }}</p>
                </td>
                <td class="hidden px-3 py-2 text-slate-600 md:table-cell">{{ role.reach }}</td>
                <td class="px-3 py-2 text-center text-slate-600">{{ role.user_count }}</td>
                <td class="px-3 py-2 text-center">
                    <IconButton icon="pencil" label="Rename" @click="openEdit(role)" />
                </td>
            </tr>
        </BaseTable>

        <BaseModal :show="!!editing" title="Rename role" @close="editing = null">
            <form id="role-edit-form" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <TextInput
                    v-model="editForm.label"
                    label="Role name"
                    required
                    :error="editForm.errors.label"
                />
                <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                    This affects the name shown throughout the system for
                    {{ editing?.user_count }}
                    {{ editing?.user_count === 1 ? 'account' : 'accounts' }}.
                    Their access does not change.
                </p>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="role-edit-form"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
