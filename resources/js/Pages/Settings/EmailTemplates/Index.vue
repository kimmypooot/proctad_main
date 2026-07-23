<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BasePagination from '@/Components/BasePagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
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
    logs: { type: Object, required: true },
    logFilters: { type: Object, default: () => ({}) },
    logStatuses: { type: Array, default: () => [] },
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

/**
 * Wraps a body fragment in a minimal document for the sandboxed iframe. Shared
 * by the template preview and the sent-email viewer so both render identically
 * — a difference between them would undermine the point of the preview.
 */
const asHtmlDoc = (body) => `<!doctype html><html><head><meta charset="utf-8">`
    + `<style>body{font-family:system-ui,-apple-system,sans-serif;font-size:14px;color:#1e293b;padding:16px;margin:0;line-height:1.6}`
    + `a{color:#2A338F}</style></head><body>${body ?? ''}</body></html>`;

const previewHtmlDoc = computed(() => (previewing.value
    ? asHtmlDoc(substitute(previewing.value.body_html, previewing.value.variables))
    : ''));

/*
 * --- Sent email log ---
 *
 * What recipients were actually sent, as opposed to what the templates above
 * say today. The body is stored at send time (see the migration), because
 * re-rendering an edited template would answer a different question than
 * "what did we tell them?".
 *
 * Fetched per row rather than shipped with the list: a rendered body is several
 * kilobytes and an admin opens one at a time.
 */
const viewingLog = ref(null);
const logLoading = ref(false);
const logError = ref(null);

const openLog = async (log) => {
    viewingLog.value = null;
    logError.value = null;
    logLoading.value = true;

    try {
        const response = await fetch(`/email-logs/${log.id}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Request failed (${response.status})`);

        viewingLog.value = await response.json();
    } catch (error) {
        logError.value = error.message;
        // Keep the modal open on failure — closing it looks like the click
        // simply did nothing.
        viewingLog.value = { subject: log.subject, body_html: null };
    } finally {
        logLoading.value = false;
    }
};

const sentBodyDoc = computed(() => (viewingLog.value ? asHtmlDoc(viewingLog.value.body_html) : ''));

const logStatusVariant = (status) => ({
    sent: 'success',
    failed: 'accent',
    skipped: 'warning',
}[status] ?? 'neutral');

/* --- Log filters (server-side; the log can run to thousands of rows) --- */
const logSearch = ref(props.logFilters?.log_search ?? '');
const logStatus = ref(props.logFilters?.log_status ?? '');
const logTemplate = ref(props.logFilters?.log_template ?? '');

const applyLogFilters = () => router.get('/email-templates', {
    log_search: logSearch.value || undefined,
    log_status: logStatus.value || undefined,
    log_template: logTemplate.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });

const resetLogFilters = () => {
    logSearch.value = '';
    logStatus.value = '';
    logTemplate.value = '';
    applyLogFilters();
};
</script>

<template>
    <Head title="Email Templates" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Email Templates"
            subtitle="Edit the content of system-sent emails. Changes take effect immediately for all future emails."
        />

        <!-- Mobile (below md): a card per template so preview/edit stay on screen. -->
        <div v-if="templates.length" class="mt-6 space-y-3 md:hidden">
            <div
                v-for="template in templates"
                :key="`m-${template.id}`"
                class="rounded-xl border border-slate-200 bg-white p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ template.name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ template.code }}</p>
                    </div>
                    <BaseBadge :variant="template.is_active ? 'success' : 'neutral'" class="shrink-0">
                        {{ template.is_active ? 'Active' : 'Inactive' }}
                    </BaseBadge>
                </div>
                <p class="mt-2 text-xs text-slate-600" :title="template.subject">{{ template.subject }}</p>
                <div class="mt-3 flex gap-1 border-t border-slate-100 pt-3">
                    <IconButton icon="eye" label="Preview" @click="openPreview(template)" />
                    <IconButton v-if="can.manage" icon="pencil" label="Edit" @click="openEdit(template)" />
                </div>
            </div>
        </div>

        <div v-if="templates.length" class="mt-6 hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
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

        <!--
            Sent email log. The templates above say what future emails will
            contain; this says what past ones actually did — the two diverge the
            moment a template is edited.
        -->
        <section class="mt-10">
            <h2 class="text-base font-semibold text-slate-900">Sent Emails</h2>
            <p class="mt-1 text-sm text-slate-500">
                Every delivery attempt, with the exact content the recipient was sent.
            </p>

            <div class="mt-4 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
                <TextInput
                    v-model="logSearch"
                    label="Search"
                    icon="magnifying-glass"
                    placeholder="Recipient or subject"
                    @keyup.enter="applyLogFilters"
                />
                <SelectInput
                    v-model="logStatus"
                    label="Status"
                    :options="[{ value: '', label: 'All statuses' }, ...logStatuses.map((s) => ({ value: s, label: s }))]"
                    @change="applyLogFilters"
                />
                <SelectInput
                    v-model="logTemplate"
                    label="Template"
                    :options="[{ value: '', label: 'All templates' }, ...templates.map((t) => ({ value: t.id, label: t.name }))]"
                    @change="applyLogFilters"
                />
            </div>
            <div v-if="logSearch || logStatus || logTemplate" class="mt-2 text-right">
                <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="resetLogFilters">
                    Clear filters
                </button>
            </div>

            <!-- Mobile (below md): a card per sent email so "view content" stays on screen. -->
            <div v-if="logs.data.length" class="mt-4 space-y-3 md:hidden">
                <div
                    v-for="log in logs.data"
                    :key="`m-${log.id}`"
                    class="rounded-xl border border-slate-200 bg-white p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ log.recipient_name || '—' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ log.recipient_email }}</p>
                        </div>
                        <BaseBadge :variant="logStatusVariant(log.status)" class="shrink-0">{{ log.status }}</BaseBadge>
                    </div>
                    <p class="mt-2 text-xs text-slate-600" :title="log.subject">{{ log.subject }}</p>
                    <p v-if="log.error_message" class="mt-0.5 text-xs text-accent-600">{{ log.error_message }}</p>
                    <dl class="mt-2 space-y-1 text-xs text-slate-500">
                        <div v-if="log.template_name" class="flex gap-1"><dt class="font-medium text-slate-400">Template:</dt><dd class="text-slate-600">{{ log.template_name }}</dd></div>
                        <div class="flex gap-1"><dt class="font-medium text-slate-400">When:</dt><dd class="text-slate-600">{{ log.at }}</dd></div>
                    </dl>
                    <div v-if="log.has_body" class="mt-3 flex gap-1 border-t border-slate-100 pt-3">
                        <IconButton icon="eye" label="View sent content" @click="openLog(log)" />
                    </div>
                </div>
            </div>

            <div v-if="logs.data.length" class="mt-4 hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Recipient</th>
                            <th class="hidden px-3 py-2 md:table-cell">Subject</th>
                            <th class="hidden px-3 py-2 lg:table-cell">Template</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="hidden px-3 py-2 sm:table-cell">When</th>
                            <th class="w-10 px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ log.recipient_name || '—' }}</p>
                                <p class="text-xs text-slate-500">{{ log.recipient_email }}</p>
                            </td>
                            <td class="hidden max-w-xs truncate px-3 py-2 text-slate-600 md:table-cell" :title="log.subject">
                                {{ log.subject }}
                            </td>
                            <td class="hidden px-3 py-2 text-slate-600 lg:table-cell">{{ log.template_name || '—' }}</td>
                            <td class="px-3 py-2">
                                <BaseBadge :variant="logStatusVariant(log.status)">{{ log.status }}</BaseBadge>
                                <p v-if="log.error_message" class="mt-0.5 max-w-[14rem] truncate text-xs text-accent-600" :title="log.error_message">
                                    {{ log.error_message }}
                                </p>
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-xs text-slate-500 sm:table-cell">{{ log.at }}</td>
                            <td class="px-3 py-2 text-center">
                                <IconButton
                                    v-if="log.has_body"
                                    icon="eye"
                                    label="View sent content"
                                    @click="openLog(log)"
                                />
                                <!-- Rows predating the body columns have nothing to open. -->
                                <span v-else class="text-xs text-slate-300" title="Content was not recorded for this email">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="mt-4">
                <EmptyState
                    icon="envelope"
                    title="No emails sent yet"
                    description="Delivery attempts appear here once the system emails someone."
                />
            </div>

            <div v-if="logs.data.length" class="mt-4">
                <BasePagination :links="logs.links" />
            </div>
        </section>

        <!-- Sent email viewer -->
        <BaseModal :show="logLoading || !!viewingLog" title="Sent Email" max-width="xl" @close="viewingLog = null; logError = null">
            <p v-if="logLoading" class="py-8 text-center text-sm text-slate-400">Loading…</p>

            <div v-else-if="viewingLog">
                <p v-if="logError" class="mb-3 rounded-lg border border-accent-200 bg-accent-50 p-3 text-sm text-accent-700">
                    Could not load the sent content: {{ logError }}
                </p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">To</p>
                        <p class="mt-0.5 text-sm text-slate-800">{{ viewingLog.recipient_name || '—' }}</p>
                        <p class="text-xs text-slate-500">{{ viewingLog.recipient_email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Sent</p>
                        <p class="mt-0.5 text-sm text-slate-800">{{ viewingLog.sent_at || viewingLog.created_at || '—' }}</p>
                        <BaseBadge v-if="viewingLog.status" class="mt-1" :variant="logStatusVariant(viewingLog.status)">
                            {{ viewingLog.status }}
                        </BaseBadge>
                    </div>
                </div>

                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-400">Subject</p>
                <p class="mt-0.5 font-semibold text-slate-900">{{ viewingLog.subject }}</p>

                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-400">Body as sent</p>
                <div class="mt-2 overflow-hidden rounded-lg border border-slate-200">
                    <!-- Sandboxed: the body is stored HTML and must never run
                         script in the admin's own page. -->
                    <iframe :srcdoc="sentBodyDoc" sandbox="" class="h-96 w-full bg-white" title="Sent email body" />
                </div>

                <p v-if="viewingLog.status === 'skipped'" class="mt-3 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800">
                    This email was never delivered — sending was switched off in Settings at the time.
                    The content above is what would have been sent.
                </p>
            </div>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="viewingLog = null; logError = null">Close</BaseButton>
            </template>
        </BaseModal>

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
