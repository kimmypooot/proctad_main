<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import IconButton from '@/Components/IconButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    pending: { type: Array, required: true },
    types: { type: Array, required: true },
    fieldOffices: { type: Array, default: null },
});

/* --- Filters (client-side; the queue is a bounded work list, not paginated) --- */
const search = ref('');
const typeFilter = ref('');
const fieldOfficeFilter = ref('');

const filtered = computed(() => props.pending.filter((c) => {
    if (typeFilter.value && c.type !== typeFilter.value) return false;
    if (fieldOfficeFilter.value && String(c.field_office_id) !== fieldOfficeFilter.value) return false;
    if (search.value) {
        const needle = search.value.toLowerCase();
        const haystack = `${c.member.name} ${c.member.proctad_id}`.toLowerCase();
        if (!haystack.includes(needle)) return false;
    }
    return true;
}));

const hasActiveFilters = computed(() => !!(search.value || typeFilter.value || fieldOfficeFilter.value));
const resetFilters = () => {
    search.value = '';
    typeFilter.value = '';
    fieldOfficeFilter.value = '';
};

/* --- Stats --- */
const oldestWaitingDays = computed(() => props.pending.reduce((max, c) => Math.max(max, c.waiting_days), 0));
const typeCounts = computed(() => props.types.map((t) => ({
    ...t,
    count: props.pending.filter((c) => c.type === t.value).length,
})));

const waitingVariant = (days) => (days >= 5 ? 'warning' : days >= 2 ? 'brand' : 'neutral');

/* --- Bulk selection --- */
const selected = ref([]);
const allFilteredSelected = computed(() => filtered.value.length > 0 && filtered.value.every((c) => selected.value.includes(c.id)));

const toggleSelectAllFiltered = () => {
    const ids = filtered.value.map((c) => c.id);
    selected.value = allFilteredSelected.value
        ? selected.value.filter((id) => !ids.includes(id))
        : [...new Set([...selected.value, ...ids])];
};

const toggleOne = (id) => {
    const index = selected.value.indexOf(id);
    if (index === -1) {
        selected.value.push(id);
    } else {
        selected.value.splice(index, 1);
    }
};

const clearSelection = () => (selected.value = []);

/* --- Single approve/disapprove --- */
const approving = ref(null);
const approveForm = useForm({});

const openApprove = (certificate) => (approving.value = certificate);

const confirmApprove = () => approveForm.post(`/certificates/${approving.value.id}/approve`, {
    preserveScroll: true,
    onSuccess: () => {
        selected.value = selected.value.filter((id) => id !== approving.value.id);
        approving.value = null;
    },
});

const disapproving = ref(null);
const disapproveForm = useForm({ remarks: '' });

const openDisapprove = (certificate) => {
    disapproving.value = certificate;
    disapproveForm.reset();
    disapproveForm.clearErrors();
};

const confirmDisapprove = () => disapproveForm.post(`/certificates/${disapproving.value.id}/disapprove`, {
    preserveScroll: true,
    onSuccess: () => {
        selected.value = selected.value.filter((id) => id !== disapproving.value.id);
        disapproving.value = null;
    },
});

/* --- Bulk approve/disapprove --- */
const showBulkApprove = ref(false);
const bulkApproveForm = useForm({ certificate_ids: [] });

const confirmBulkApprove = () => {
    bulkApproveForm.certificate_ids = selected.value;
    bulkApproveForm.post('/certificates/bulk-approve', {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            showBulkApprove.value = false;
        },
    });
};

const showBulkDisapprove = ref(false);
const bulkDisapproveForm = useForm({ certificate_ids: [], remarks: '' });

const openBulkDisapprove = () => {
    showBulkDisapprove.value = true;
    bulkDisapproveForm.reset();
    bulkDisapproveForm.clearErrors();
};

const confirmBulkDisapprove = () => {
    bulkDisapproveForm.certificate_ids = selected.value;
    bulkDisapproveForm.post('/certificates/bulk-disapprove', {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            showBulkDisapprove.value = false;
        },
    });
};
</script>

<template>
    <Head title="Approvals" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Pending Approvals"
            subtitle="Certificate and Designation Order requests awaiting your decision — oldest first."
        />

        <template v-if="pending.length">
            <!-- Stats -->
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard compact label="Total Pending" :value="pending.length" icon="clipboard-check" />
                <StatCard
                    compact
                    label="Longest Waiting"
                    :value="`${oldestWaitingDays.toFixed(2)} days`"
                    icon="clock"
                    :accent="oldestWaitingDays >= 5 ? 'amber' : 'brand'"
                />
                <StatCard
                    v-for="type in typeCounts"
                    :key="type.value"
                    compact
                    :label="type.label"
                    :value="type.count"
                    icon="document-check"
                    accent="emerald"
                />
            </div>

            <!-- Filters -->
            <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput v-model="search" label="Search" placeholder="Member name or PROCTAD ID" />
                <SelectInput
                    v-model="typeFilter"
                    label="Type"
                    placeholder="All types"
                    :options="[{ value: '', label: 'All types' }, ...types]"
                />
                <SelectInput
                    v-if="fieldOffices"
                    v-model="fieldOfficeFilter"
                    label="Testing Center"
                    placeholder="All field offices"
                    :options="[{ value: '', label: 'All field offices' }, ...fieldOffices.map((fo) => ({ value: String(fo.id), label: fo.name }))]"
                />
            </div>
            <div v-if="hasActiveFilters" class="mt-2 flex items-center justify-between text-sm text-slate-500">
                <span>{{ filtered.length }} of {{ pending.length }} request(s)</span>
                <button type="button" class="font-medium text-brand-700 hover:text-brand-800 hover:underline" @click="resetFilters">
                    Clear filters
                </button>
            </div>

            <!-- Bulk action bar -->
            <div v-if="selected.length" class="sticky top-2 z-10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 shadow-sm">
                <p class="text-sm font-medium text-brand-800">{{ selected.length }} request(s) selected</p>
                <div class="flex flex-wrap gap-2">
                    <BaseButton variant="outline" size="sm" @click="clearSelection">Clear</BaseButton>
                    <BaseButton variant="outline" size="sm" @click="openBulkDisapprove">
                        Disapprove Selected
                    </BaseButton>
                    <BaseButton variant="primary" size="sm" @click="showBulkApprove = true">
                        <AppIcon name="check-circle" class="h-4 w-4" />
                        Approve & Release Selected
                    </BaseButton>
                </div>
            </div>

            <!-- Queue table -->
            <div v-if="filtered.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-8 px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                                    :checked="allFilteredSelected"
                                    @change="toggleSelectAllFiltered"
                                >
                            </th>
                            <th class="px-3 py-2">Member</th>
                            <th class="hidden px-3 py-2 sm:table-cell">Type</th>
                            <th class="hidden px-3 py-2 lg:table-cell">Testing Center</th>
                            <th class="hidden px-3 py-2 xl:table-cell">Source</th>
                            <th class="px-3 py-2">Waiting</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="certificate in filtered" :key="certificate.id" class="transition-colors hover:bg-brand-50/40">
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 accent-brand-600"
                                    :checked="selected.includes(certificate.id)"
                                    @change="toggleOne(certificate.id)"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ certificate.member.name }}</p>
                                <p class="font-mono text-xs text-brand-700">{{ certificate.member.proctad_id }}</p>
                                <p class="mt-0.5 text-xs text-slate-400 sm:hidden">{{ certificate.type_label }}</p>
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ certificate.type_label }}</td>
                            <td class="hidden max-w-[10rem] truncate px-3 py-2 text-slate-600 lg:table-cell" :title="certificate.field_office ?? ''">
                                {{ certificate.field_office ?? '—' }}
                            </td>
                            <td class="hidden max-w-[12rem] truncate px-3 py-2 text-slate-600 xl:table-cell" :title="certificate.source">
                                {{ certificate.source }}
                                <span v-if="certificate.source_date" class="text-slate-400"> · {{ certificate.source_date }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <BaseBadge :variant="waitingVariant(certificate.waiting_days)" size="xs">{{ certificate.requested_ago }}</BaseBadge>
                                <p class="mt-1 text-xs text-slate-400">{{ certificate.requested_by }}</p>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="inline-flex gap-1">
                                    <IconButton
                                        icon="check-circle"
                                        label="Approve & Release"
                                        @click="openApprove(certificate)"
                                    />
                                    <IconButton
                                        icon="x-circle"
                                        label="Disapprove"
                                        variant="danger"
                                        @click="openDisapprove(certificate)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="mt-4">
                <EmptyState
                    icon="clipboard-check"
                    title="No requests match your filters"
                    description="Try adjusting or clearing the filters above."
                />
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="clipboard-check"
                title="Nothing pending"
                description="You're all caught up — no approval requests waiting on your decision."
            />
        </div>

        <!-- Approve modal (single) -->
        <BaseModal :show="!!approving" title="Approve & release certificate" @close="approving = null">
            <p class="text-sm leading-relaxed text-slate-600 text-justify indent-6">
                Approve and immediately release <strong>{{ approving?.type_label }}</strong> for
                <strong>{{ approving?.member.name }}</strong>? This mints the certificate number, renders the PDF,
                and emails it to the member right away — this cannot be undone.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="approving = null">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="approveForm.processing"
                    :disabled="approveForm.processing"
                    @click="confirmApprove"
                >
                    <AppIcon name="check-circle" class="h-4 w-4" />
                    Approve & Release
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Approve modal (bulk) -->
        <BaseModal :show="showBulkApprove" title="Approve & release selected" @close="showBulkApprove = false">
            <p class="text-sm leading-relaxed text-slate-600 text-justify indent-6">
                Approve and immediately release <strong>{{ selected.length }}</strong> request(s)? Each certificate
                number is minted, its PDF rendered, and emailed to the member right away — this cannot be undone.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showBulkApprove = false">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="bulkApproveForm.processing"
                    :disabled="bulkApproveForm.processing"
                    @click="confirmBulkApprove"
                >
                    <AppIcon name="check-circle" class="h-4 w-4" />
                    Approve & Release {{ selected.length }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Disapprove modal (single) -->
        <BaseModal :show="!!disapproving" title="Disapprove request" @close="disapproving = null">
            <p class="text-sm leading-relaxed text-slate-600 text-justify indent-6">
                Disapproving <strong>{{ disapproving?.type_label }}</strong> for
                <strong>{{ disapproving?.member.name }}</strong> requires a reason. The requester will be able to
                see this remark.
            </p>
            <TextArea
                v-model="disapproveForm.remarks"
                label="Reason for disapproval"
                required
                class="mt-4"
                placeholder="Explain why this request is being disapproved."
                :error="disapproveForm.errors.remarks"
            />
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="disapproving = null">Cancel</BaseButton>
                <BaseButton
                    variant="accent"
                    size="sm"
                    :loading="disapproveForm.processing"
                    :disabled="disapproveForm.processing"
                    @click="confirmDisapprove"
                >
                    Disapprove
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Disapprove modal (bulk) -->
        <BaseModal :show="showBulkDisapprove" title="Disapprove selected requests" @close="showBulkDisapprove = false">
            <p class="text-sm leading-relaxed text-slate-600 text-justify indent-6">
                Disapproving <strong>{{ selected.length }}</strong> request(s) requires one shared reason, applied to
                all of them. Each requester will be able to see this remark.
            </p>
            <TextArea
                v-model="bulkDisapproveForm.remarks"
                label="Reason for disapproval"
                required
                class="mt-4"
                placeholder="Explain why these requests are being disapproved."
                :error="bulkDisapproveForm.errors.remarks"
            />
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showBulkDisapprove = false">Cancel</BaseButton>
                <BaseButton
                    variant="accent"
                    size="sm"
                    :loading="bulkDisapproveForm.processing"
                    :disabled="bulkDisapproveForm.processing"
                    @click="confirmBulkDisapprove"
                >
                    Disapprove {{ selected.length }}
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>
