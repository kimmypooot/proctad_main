<script setup>
import { computed, ref } from 'vue';
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

/* --- Preview: substitute {placeholder} variables with sample values so admins
   can see roughly how the email will look, without sending a real one. --- */
const SAMPLE_VALUES = {
    member_name: 'Juan Dela Cruz',
    exam_name: 'March 2026 CSE-PPT',
    exam_date: 'March 15, 2026 (Sunday)',
    designation: 'Room Examiner',
    proctad_id: 'PROCTAD-CSCRO8-AB12CD',
    confirmation_url: 'https://proctad.example/assignments/123/confirm?signature=sample',
};

const sampleFor = (key, description) => SAMPLE_VALUES[key] ?? `[${description || key}]`;

const substitute = (text, variables) => (text ?? '').replace(
    /\{(\w+)\}/g,
    (match, key) => sampleFor(key, variables?.[key]),
);

const previewing = ref(null);

const openPreview = (template) => {
    previewing.value = { subject: template.subject, body_html: template.body_html, variables: template.variables };
};

const previewDraft = () => {
    previewing.value = { subject: form.subject, body_html: form.body_html, variables: editing.value?.variables };
};

const previewSubject = computed(() => (previewing.value ? substitute(previewing.value.subject, previewing.value.variables) : ''));

const previewHtmlDoc = computed(() => {
    if (!previewing.value) return '';
    const body = substitute(previewing.value.body_html, previewing.value.variables);
    return `<!doctype html><html><head><meta charset="utf-8">`
        + `<style>body{font-family:system-ui,-apple-system,sans-serif;font-size:14px;color:#1e293b;padding:16px;margin:0;line-height:1.6}`
        + `a{color:#2A338F}</style></head><body>${body}</body></html>`;
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
                            <div class="inline-flex gap-1">
                                <IconButton icon="eye" label="Preview" @click="openPreview(template)" />
                                <IconButton v-if="can.manage" icon="pencil" label="Edit" @click="openEdit(template)" />
                            </div>
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
                <BaseButton variant="outline" size="sm" @click="previewDraft">Preview</BaseButton>
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

        <!-- Preview modal -->
        <BaseModal :show="!!previewing" title="Email Preview" max-width="xl" @close="previewing = null">
            <div v-if="previewing">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Subject</p>
                <p class="mt-0.5 font-semibold text-slate-900">{{ previewSubject }}</p>

                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-400">Body</p>
                <div class="mt-2 overflow-hidden rounded-lg border border-slate-200">
                    <iframe :srcdoc="previewHtmlDoc" sandbox="" class="h-96 w-full bg-white" title="Email body preview" />
                </div>

                <p class="mt-3 rounded-lg bg-slate-50 p-2.5 text-xs text-slate-500">
                    Placeholder variables are filled with sample values for this preview — actual emails substitute
                    the real member/exam data.
                </p>
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="previewing = null">Close</BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
