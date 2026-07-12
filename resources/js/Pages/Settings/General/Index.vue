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

defineProps({
    settings: { type: Array, required: true },
    can: { type: Object, required: true },
});

const typeOptions = ['string', 'number', 'boolean', 'json'];

const showCreate = ref(false);
const editing = ref(null);
const deleting = ref(null);

const createForm = useForm({ key: '', value: '', type: 'string', description: '', is_public: false });
const editForm = useForm({ value: '', description: '', is_public: false });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreate.value = true;
};

const submitCreate = () => createForm.post('/settings', {
    preserveScroll: true,
    onSuccess: () => (showCreate.value = false),
});

const openEdit = (setting) => {
    editing.value = setting;
    editForm.clearErrors();
    editForm.value = setting.value ?? '';
    editForm.description = setting.description ?? '';
    editForm.is_public = setting.is_public;
};

const submitEdit = () => editForm.put(`/settings/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});

const destroyForm = useForm({});
const confirmDelete = () => destroyForm.delete(`/settings/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (deleting.value = null),
});
</script>

<template>
    <Head title="System Settings" />

    <DashboardLayout>
        <DashboardPageHeader title="System Settings">
            <template #subtitle>
                Runtime key-value configuration. SMTP credentials are managed via <code class="text-xs">.env</code>, not here.
            </template>
            <template v-if="can.manage" #actions>
                <BaseButton variant="primary" size="sm" @click="openCreate">
                    Add Setting
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="settings.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Key</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Description</th>
                        <th class="hidden px-3 py-2 md:table-cell">Visibility</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="setting in settings" :key="setting.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-mono text-xs font-medium text-slate-900">{{ setting.key }}</td>
                        <td class="max-w-[10rem] truncate px-3 py-2 text-slate-600" :title="setting.value || ''">{{ setting.value || '—' }}</td>
                        <td class="hidden px-3 py-2 sm:table-cell"><BaseBadge variant="neutral">{{ setting.type }}</BaseBadge></td>
                        <td class="hidden max-w-xs truncate px-3 py-2 text-slate-500 xl:table-cell" :title="setting.description || ''">{{ setting.description || '—' }}</td>
                        <td class="hidden px-3 py-2 md:table-cell">
                            <BaseBadge :variant="setting.is_public ? 'success' : 'neutral'">
                                {{ setting.is_public ? 'Public' : 'Internal' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="can.manage" class="inline-flex gap-1">
                                <IconButton icon="pencil" label="Edit" @click="openEdit(setting)" />
                                <IconButton icon="trash" label="Remove" variant="danger" @click="deleting = setting" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="cog-6-tooth"
                title="No settings configured"
                description="Add a key-value setting to control runtime behavior without a deploy."
            />
        </div>

        <!-- Create modal -->
        <BaseModal :show="showCreate" title="Add Setting" @close="showCreate = false">
            <form id="setting-create-form" class="space-y-4" novalidate @submit.prevent="submitCreate">
                <TextInput
                    v-model="createForm.key"
                    label="Key"
                    required
                    :error="createForm.errors.key"
                    placeholder="e.g. reminder_days_before_exam"
                />
                <TextInput v-model="createForm.value" label="Value" optional :error="createForm.errors.value" />
                <SelectInput v-model="createForm.type" label="Type" required :options="typeOptions" :error="createForm.errors.type" />
                <TextInput v-model="createForm.description" label="Description" optional :error="createForm.errors.description" />
                <CheckboxInput v-model="createForm.is_public">Public (safe to expose to the member portal / public pages)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showCreate = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="setting-create-form"
                    variant="primary"
                    size="sm"
                    :loading="createForm.processing"
                    :disabled="createForm.processing"
                >
                    Add Setting
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Edit modal -->
        <BaseModal :show="!!editing" :title="`Edit “${editing?.key}”`" @close="editing = null">
            <form id="setting-edit-form" class="space-y-4" novalidate @submit.prevent="submitEdit">
                <TextInput v-model="editForm.value" label="Value" optional :error="editForm.errors.value" />
                <TextInput v-model="editForm.description" label="Description" optional :error="editForm.errors.description" />
                <CheckboxInput v-model="editForm.is_public">Public</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="setting-edit-form"
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                >
                    Save Changes
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Delete confirm -->
        <BaseModal :show="!!deleting" title="Remove setting" @close="deleting = null">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong class="font-mono">{{ deleting?.key }}</strong>? Any code reading this key will fall back to its default.
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
