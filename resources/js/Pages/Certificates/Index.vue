<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BasePagination from '@/Components/BasePagination.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import StepTabs from '@/Components/StepTabs.vue';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    certificates: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, required: true },
    types: { type: Array, required: true },
    signatories: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const type = ref(props.filters.type ?? '');
const loading = ref(false);

const statusTabs = computed(() => [
    { key: '', label: `All (${props.stats.total})` },
    { key: 'pending', label: `Pending Approval (${props.stats.pending})` },
    { key: 'released', label: `Released (${props.stats.released})` },
    { key: 'disapproved', label: `Disapproved (${props.stats.disapproved})` },
]);

let debounce = null;

const applyFilters = () => router.get('/certificates', {
    search: search.value || undefined,
    status: status.value || undefined,
    type: type.value || undefined,
}, {
    preserveState: true,
    replace: true,
    only: ['certificates', 'filters'],
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
});

const onSearchInput = () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
};

/* --- PDF view/preview modal --- */
const viewing = ref(null);
const viewCertificate = (certificate) => (viewing.value = { certificate, mode: 'view' });
const previewCertificate = (certificate) => (viewing.value = { certificate, mode: 'preview' });

/* --- Regenerate a single released certificate's PDF --- */
const regenTarget = ref(null);
const regenForm = useForm({});
const submitRegenerate = () => {
    if (!regenTarget.value) return;
    regenForm.post(`/certificates/${regenTarget.value.id}/regenerate`, {
        preserveScroll: true,
        onSuccess: () => (regenTarget.value = null),
    });
};

/* --- Bulk re-sign (super admin only) --- */
const selected = ref([]);
const showResign = ref(false);
const resignForm = useForm({ certificate_ids: [], signatory_id: '' });

const releasedIds = () => props.certificates.data.filter((c) => c.status === 'released').map((c) => c.id);
const toggleSelectAllReleased = (checked) => {
    selected.value = checked ? releasedIds() : [];
};

const submitResign = () => {
    resignForm.certificate_ids = selected.value;
    resignForm.post('/certificates/bulk-resign', {
        preserveScroll: true,
        onSuccess: () => {
            showResign.value = false;
            selected.value = [];
            resignForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Certificates" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Certificates"
            subtitle="Appearance, Appreciation, Completion certificates, and Designation Orders."
        />

        <!-- Stats -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard compact label="Total Certificates" :value="stats.total" icon="document-check" />
            <StatCard compact label="Released" :value="stats.released" icon="check-badge" accent="emerald" />
            <StatCard compact label="Pending Approval" :value="stats.pending" icon="clock" accent="amber" />
            <StatCard compact label="Disapproved" :value="stats.disapproved" icon="x-circle" accent="slate" />
        </div>

        <!-- Status tabs -->
        <div class="mt-6">
            <StepTabs v-model="status" :steps="statusTabs" aria-label="Certificate status filter" @update:model-value="applyFilters" />
        </div>

        <!-- Filters -->
        <div class="mt-4 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
            <TextInput
                v-model="search"
                label="Search"
                placeholder="Certificate no., name, or PROCTAD ID"
                @update:model-value="onSearchInput"
            />
            <SelectInput
                v-model="type"
                label="Type"
                placeholder="All types"
                :options="[{ value: '', label: 'All types' }, ...types]"
                @update:model-value="applyFilters"
            />
        </div>
        <p class="mt-2 text-sm text-slate-500">{{ certificates.total }} certificate(s) found.</p>

        <div v-if="can.bulkResign && selected.length" class="mt-4 flex items-center justify-between rounded-lg border border-brand-200 bg-brand-50 px-4 py-3">
            <p class="text-sm font-medium text-brand-800">{{ selected.length }} certificate(s) selected</p>
            <BaseButton variant="secondary" size="sm" @click="showResign = true">Re-sign Selected</BaseButton>
        </div>

        <div v-if="loading || certificates.data.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th v-if="can.bulkResign" class="w-8 px-3 py-2">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                                :checked="releasedIds().length > 0 && selected.length === releasedIds().length"
                                @change="toggleSelectAllReleased($event.target.checked)"
                            >
                        </th>
                        <th class="px-3 py-2">Certificate No.</th>
                        <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                        <th class="px-3 py-2">Member</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Testing Center</th>
                        <th class="hidden px-3 py-2 xl:table-cell">Source</th>
                        <th class="hidden px-3 py-2 lg:table-cell">Released</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-16 px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <TableSkeleton v-if="loading" :columns="can.bulkResign ? 9 : 8" />
                    <tr v-for="certificate in loading ? [] : certificates.data" :key="certificate.id" class="transition-colors hover:bg-brand-50/40">
                        <td v-if="can.bulkResign" class="px-3 py-2">
                            <input
                                v-if="certificate.status === 'released'"
                                v-model="selected"
                                type="checkbox"
                                :value="certificate.id"
                                class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                            >
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-semibold text-brand-700">
                            {{ certificate.certificate_no ?? '— pending —' }}
                            <p class="font-sans font-normal text-slate-400 sm:hidden">{{ certificate.type_label }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ certificate.type_label }}</td>
                        <td class="max-w-[10rem] px-3 py-2 sm:max-w-[12rem]">
                            <p class="truncate font-medium text-slate-900" :title="certificate.member.name">{{ certificate.member.name }}</p>
                            <p class="font-mono text-xs text-brand-700">{{ certificate.member.proctad_id }}</p>
                        </td>
                        <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="certificate.field_office ?? ''">{{ certificate.field_office ?? '—' }}</td>
                        <td class="hidden px-3 py-2 text-slate-600 xl:table-cell">{{ certificate.source }}</td>
                        <td class="hidden px-3 py-2 text-slate-500 lg:table-cell">{{ certificate.released_at ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <BaseBadge :variant="certificate.status_variant">{{ certificate.status_label }}</BaseBadge>
                            <p
                                v-if="certificate.disapproval_remarks"
                                class="mt-1 max-w-[12rem] truncate text-xs text-accent-600"
                                :title="certificate.disapproval_remarks"
                            >
                                {{ certificate.disapproval_remarks }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div v-if="certificate.downloadable" class="inline-flex gap-1">
                                <IconButton icon="eye" label="View" @click="viewCertificate(certificate)" />
                                <IconButton
                                    icon="arrow-down-tray"
                                    label="Download"
                                    external
                                    :href="`/certificates/${certificate.id}/download`"
                                />
                                <IconButton
                                    v-if="certificate.regenerable"
                                    icon="arrow-path"
                                    label="Regenerate PDF"
                                    @click="regenTarget = certificate"
                                />
                            </div>
                            <div v-else-if="certificate.previewable" class="inline-flex gap-1">
                                <IconButton icon="eye" label="Preview" @click="previewCertificate(certificate)" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-4">
            <EmptyState
                icon="document-check"
                title="No certificates found"
                description="No certificates match your current filters."
            />
        </div>

        <div class="mt-6">
            <BasePagination :links="certificates.links" />
        </div>

        <!-- View/Preview certificate modal -->
        <BaseModal
            :show="!!viewing"
            :title="viewing?.mode === 'preview' ? 'Certificate Preview (not yet released)' : (viewing?.certificate.certificate_no ?? 'Certificate')"
            max-width="3xl"
            @close="viewing = null"
        >
            <div v-if="viewing" class="-mx-6 -my-5 overflow-hidden rounded-b-xl bg-slate-100">
                <p v-if="viewing.mode === 'preview'" class="bg-amber-50 px-4 py-2 text-xs text-amber-800">
                    This is a live preview using the current signatory and letterhead — it is watermarked and not
                    saved. The final certificate is generated only once this request is approved.
                </p>
                <iframe
                    :key="`${viewing.mode}-${viewing.certificate.id}`"
                    :src="`/certificates/${viewing.certificate.id}/${viewing.mode}`"
                    :title="`${viewing.certificate.certificate_no ?? 'Certificate'} ${viewing.mode}`"
                    class="h-[75vh] w-full border-0"
                />
            </div>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="viewing = null">Close</BaseButton>
                <BaseButton
                    v-if="viewing?.mode === 'view'"
                    variant="primary"
                    size="sm"
                    external
                    :href="`/certificates/${viewing.certificate.id}/download`"
                >
                    <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                    Download
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Regenerate PDF confirmation -->
        <BaseModal :show="!!regenTarget" title="Regenerate Certificate PDF" @close="regenTarget = null">
            <p class="text-sm text-slate-600">
                Re-render the PDF for <strong class="font-mono">{{ regenTarget?.certificate_no }}</strong>
                using the current template and active letterhead. The certificate number, signatory, and
                release date stay the same.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="regenTarget = null">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="regenForm.processing"
                    :disabled="regenForm.processing"
                    @click="submitRegenerate"
                >
                    Regenerate
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Bulk re-sign modal -->
        <BaseModal :show="showResign" title="Re-sign Selected Certificates" @close="showResign = false">
            <form id="resign-form" class="space-y-4" novalidate @submit.prevent="submitResign">
                <p class="text-sm text-slate-600">
                    Replace the signatory on <strong>{{ selected.length }}</strong> released certificate(s) and
                    regenerate their PDFs. Certificate numbers and release dates are unchanged.
                </p>
                <SelectInput
                    v-model="resignForm.signatory_id"
                    label="New Signatory"
                    required
                    placeholder="Select a signatory"
                    :options="signatories.map((s) => ({ value: s.id, label: `${s.name} — ${s.position}` }))"
                    :error="resignForm.errors.signatory_id"
                />
            </form>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showResign = false">Cancel</BaseButton>
                <BaseButton
                    type="submit"
                    form="resign-form"
                    variant="primary"
                    size="sm"
                    :loading="resignForm.processing"
                    :disabled="resignForm.processing"
                >
                    Re-sign
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
