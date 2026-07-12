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
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    templates: { type: Array, required: true },
    can: { type: Object, required: true },
});

const editing = ref(null);
const form = useForm({ name: '', subject: '', body_html: '', body_plain: '', is_active: true });

const openEdit = (template) => {
    editing.value = template;
    form.clearErrors();
    form.name = template.name;
    form.subject = template.subject;
    form.body_html = template.body_html;
    form.body_plain = template.body_plain ?? '';
    form.is_active = template.is_active;
};

const submit = () => form.put(`/email-templates/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
});
</script>

<template>
    <Head title="Email Templates" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Email Templates"
            subtitle="Edit the content of system-sent emails. Changes take effect immediately for all future emails."
        />

        <div v-if="templates.length" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Code</th>
                        <th class="hidden px-3 py-2 md:table-cell">Subject</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-10 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="template in templates" :key="template.id" class="transition-colors hover:bg-brand-50/40">
                        <td class="px-3 py-2 font-medium text-slate-900">{{ template.name }}</td>
                        <td class="hidden whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-500 sm:table-cell">{{ template.code }}</td>
                        <td class="hidden max-w-xs truncate px-3 py-2 text-slate-600 md:table-cell" :title="template.subject">{{ template.subject }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="template.is_active ? 'success' : 'neutral'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <IconButton v-if="can.manage" icon="pencil" label="Edit" @click="openEdit(template)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="envelope"
                title="No email templates"
                description="Templates seeded by the system will appear here."
            />
        </div>

        <!-- Edit modal -->
        <BaseModal :show="!!editing" title="Edit Email Template" max-width="xl" @close="editing = null">
            <form id="template-form" class="space-y-4" novalidate @submit.prevent="submit">
                <p v-if="editing?.variables?.length || (editing && Object.keys(editing.variables || {}).length)" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                    Available variables:
                    <code
                        v-for="(desc, key) in editing?.variables"
                        :key="key"
                        class="ml-1 rounded bg-white px-1.5 py-0.5 font-mono text-brand-700"
                        :title="desc"
                    >{{ '{' + key + '}' }}</code>
                </p>
                <TextInput v-model="form.name" label="Internal Name" required :error="form.errors.name" />
                <TextInput v-model="form.subject" label="Subject" required :error="form.errors.subject" hint="Supports {placeholder} variables." />
                <TextArea v-model="form.body_html" label="HTML Body" required :rows="10" :error="form.errors.body_html" />
                <TextArea v-model="form.body_plain" label="Plain Text Body" optional :rows="6" :error="form.errors.body_plain" />
                <CheckboxInput v-model="form.is_active">Active (used when sending this email type)</CheckboxInput>
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="editing = null">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="template-form"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    Save Changes
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
