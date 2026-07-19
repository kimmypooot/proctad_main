<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EditMemberModal from './EditMemberModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MemberIdCard from '@/Components/MemberIdCard.vue';
import QrCode from '@/Components/QrCode.vue';
import StepTabs from '@/Components/StepTabs.vue';
import { useDetailsResource } from '@/Composables/useDetailsResource';

const props = defineProps({
    show: { type: Boolean, required: true },
    memberId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const { loading, data: raw, error, load } = useDetailsResource(
    () => `/members/${props.memberId}/details`,
    'Could not load member details.',
);

const member = () => raw.value?.member ?? null;
const requirements = () => raw.value?.requirements ?? [];
const compliedCount = () => raw.value?.compliedCount ?? 0;
const serviceHistory = () => raw.value?.serviceHistory ?? [];
const idCard = () => raw.value?.idCard ?? null;
const can = () => raw.value?.can ?? { update: false };

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const deleting = ref(false);

const openEditModal = () => {
    showEditModal.value = true;
};

const onEditSaved = () => {
    if (props.memberId) {
        fetchMember();
    }
};

const rows = reactive({});

const activeTab = ref('details');

const tabs = computed(() => [
    { key: 'details', label: 'Details' },
    {
        key: 'requirements',
        label: `Requirements (${compliedCount()}/${requirements().length})`,
        complete: requirements().length > 0 && compliedCount() === requirements().length,
    },
    { key: 'id', label: 'Digital ID' },
    { key: 'history', label: `Service History (${serviceHistory().length})` },
]);

watch(() => props.show, (open) => {
    if (open && props.memberId) {
        activeTab.value = 'details';
        fetchMember();
    }
});

const fetchMember = async () => {
    const json = await load();

    for (const key of Object.keys(rows)) {
        delete rows[key];
    }

    if (json === null) return;

    for (const req of json.requirements) {
        rows[req.key] = {
            complied: req.complied,
            remarks: req.remarks ?? '',
            file: null,
            saving: false,
        };
    }
};

const deleteMember = () => {
    deleting.value = true;
    router.delete(`/members/${props.memberId}`, {
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
            emit('close');
        },
    });
};

const saveRequirement = (req) => {
    const row = rows[req.key];
    row.saving = true;
    router.post(`/members/${props.memberId}/requirements/${req.key}`, {
        _method: 'put',
        complied: row.complied,
        remarks: row.remarks || null,
        file: row.file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            row.saving = false;
            row.file = null;
        },
    });
};

const onFileChange = (req, event) => {
    rows[req.key].file = event.target.files[0] ?? null;
};

const printCard = () => window.print();

const qrRef = ref(null);
const showQrModal = ref(false);

const downloadQr = () => {
    const dataUrl = qrRef.value?.toDataUrl();
    if (!dataUrl) return;

    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = `PROCTAD-${member().proctad_id}-QR.png`;
    link.click();
};

const detailItems = (m) => [
    ['Sex', m.sex === 'male' ? 'Male' : 'Female'],
    ['Date of Birth', m.date_of_birth ?? '—'],
    ['Email', m.email],
    ['Mobile Number', m.mobile_number],
    ['Agency', m.agency],
    ['Testing Center', m.field_office?.name ?? '—'],
    ['Position', m.position ?? '—'],
    ['Registered', m.created_at],
];
</script>

<template>
    <BaseModal :show="show" title="Member Details" max-width="4xl" @close="emit('close')">
        <div v-if="loading" class="max-h-[75vh] space-y-6 overflow-y-auto -mx-6 -mt-5 px-6 pt-5 animate-pulse">
            <!-- Header skeleton -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="h-12 w-12 shrink-0 rounded-full bg-slate-200" />
                    <div class="space-y-2">
                        <div class="h-3 w-32 rounded bg-slate-200" />
                        <div class="h-5 w-56 rounded bg-slate-200" />
                        <div class="flex gap-2">
                            <div class="h-5 w-16 rounded-full bg-slate-200" />
                            <div class="h-5 w-28 rounded-full bg-slate-200" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <div class="h-8 w-16 rounded-lg bg-slate-200" />
                    <div class="h-8 w-20 rounded-lg bg-slate-200" />
                </div>
            </div>

            <!-- Tab bar skeleton -->
            <div class="flex gap-4 border-b border-slate-200 pb-2">
                <div v-for="i in 4" :key="i" class="h-4 w-24 rounded bg-slate-200" />
            </div>

            <!-- Details tab skeleton -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 8" :key="i" class="space-y-1.5">
                    <div class="h-3 w-16 rounded bg-slate-200" />
                    <div class="h-4 w-28 rounded bg-slate-200" />
                </div>
            </div>
        </div>

        <template v-else-if="raw">
            <div class="-mx-6 -mt-5 space-y-4 border-b border-slate-200 px-6 pt-5">
                <!-- Header with photo -->
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="shrink-0">
                            <img
                                v-if="member().photo_url"
                                :src="member().photo_url"
                                :alt="member().name"
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-100"
                            >
                            <div
                                v-else
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 ring-2 ring-slate-100"
                            >
                                {{ member().first_name?.[0] }}{{ member().last_name?.[0] }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="break-words text-xs font-medium uppercase tracking-wide text-brand-700">{{ member().proctad_id }}</p>
                            <h3 class="break-words text-xl font-semibold text-slate-900">{{ member().name }}</h3>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <BaseBadge :variant="member().status_variant">{{ member().status_label }}</BaseBadge>
                                <BaseBadge v-if="member().field_office" variant="neutral">
                                    <AppIcon name="building-office" class="h-3.5 w-3.5" />
                                    {{ member().field_office.name }}
                                </BaseBadge>
                            </div>
                        </div>
                    </div>
                    <div v-if="can().update" class="flex shrink-0 gap-2">
                        <BaseButton variant="outline" size="sm" @click="openEditModal">Edit</BaseButton>
                        <BaseButton variant="ghost" size="sm" @click="showDeleteModal = true">
                            <span class="text-accent-600">Remove</span>
                        </BaseButton>
                    </div>
                </div>

                <!-- Disqualification banner -->
                <div
                    v-if="member().status === 'disqualified' && member().disqualification_remarks"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    <strong>Disqualification remarks:</strong> {{ member().disqualification_remarks }}
                </div>

                <StepTabs v-model="activeTab" :steps="tabs" aria-label="Member details sections" />
            </div>

            <div class="-mx-6 max-h-[60vh] overflow-y-auto px-6 py-5">
                <!-- Details -->
                <div v-if="activeTab === 'details'">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="[label, value] in detailItems(member())" :key="label" class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</dt>
                            <dd class="mt-0.5 break-words text-slate-700">{{ value }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Eligibility Requirements -->
                <div v-else-if="activeTab === 'requirements'" class="min-w-0">
                    <div class="flex items-center justify-between">
                        <h4 class="text-base font-semibold text-slate-900">Eligibility Requirements</h4>
                        <BaseBadge :variant="compliedCount() === requirements().length ? 'success' : 'warning'">
                            {{ compliedCount() }} / {{ requirements().length }} complied
                        </BaseBadge>
                    </div>

                    <ul class="mt-4 divide-y divide-slate-100">
                        <li v-for="req in requirements()" :key="req.key" class="py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                                        :class="req.complied ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'"
                                    >
                                        <AppIcon :name="req.complied ? 'check' : 'x-mark'" class="h-3.5 w-3.5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-medium text-slate-800">{{ req.label }}</p>
                                        <p v-if="!can().update && req.remarks" class="mt-0.5 break-words text-xs text-slate-500">
                                            {{ req.remarks }}
                                        </p>
                                    </div>
                                </div>
                                <a
                                    v-if="req.has_file"
                                    :href="`/members/${member().id}/requirements/${req.key}/download`"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"
                                >
                                    <AppIcon name="document-text" class="h-4 w-4" />
                                    Download file
                                </a>
                            </div>
    
                            <div v-if="can().update" class="mt-3 grid gap-3 pl-9 sm:grid-cols-[auto_1fr_auto_auto] sm:items-center">
                                <CheckboxInput v-model="rows[req.key].complied">Complied</CheckboxInput>
                                <input
                                    v-model="rows[req.key].remarks"
                                    type="text"
                                    placeholder="Remarks (optional)"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
                                >
                                <label class="min-w-0 cursor-pointer text-xs font-medium text-slate-500 hover:text-brand-700">
                                    <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" @change="onFileChange(req, $event)">
                                    <span class="flex items-center gap-1">
                                        <AppIcon name="cloud-arrow-up" class="h-4 w-4 shrink-0" />
                                        <span class="truncate" :title="rows[req.key].file?.name">
                                            {{ rows[req.key].file ? rows[req.key].file.name : 'Attach file' }}
                                        </span>
                                    </span>
                                </label>
                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    :loading="rows[req.key].saving"
                                    :disabled="rows[req.key].saving"
                                    @click="saveRequirement(req)"
                                >
                                    Save
                                </BaseButton>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Digital ID -->
                <div v-else-if="activeTab === 'id'" class="grid gap-6 md:grid-cols-2">
                    <div class="flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-6">
                        <div class="flex items-center justify-between gap-2 print:hidden">
                            <h4 class="text-base font-semibold text-slate-900">ID Card</h4>
                            <BaseButton variant="outline" size="sm" @click="printCard">Print ID Card</BaseButton>
                        </div>
                        <div id="print-id-card" class="mt-4 flex flex-1 items-center justify-center">
                            <MemberIdCard :card="idCard()" />
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-6 print:hidden">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-base font-semibold text-slate-900">QR Code</h4>
                            <div class="flex gap-2">
                                <BaseButton variant="outline" size="sm" @click="showQrModal = true">View</BaseButton>
                                <BaseButton variant="outline" size="sm" @click="downloadQr">Download</BaseButton>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-1 flex-col items-center justify-center gap-3">
                            <QrCode ref="qrRef" :value="idCard().qr_value" :size="200" />
                            <p class="break-all text-center text-xs text-slate-500">{{ idCard().qr_value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Service History -->
                <div v-else-if="activeTab === 'history'">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-slate-900">Service History</h4>
                        <div class="flex gap-2">
                            <BaseButton :href="`/members/${member().id}/service-history/print`" external target="_blank" variant="outline" size="sm">
                                Print
                            </BaseButton>
                            <BaseButton :href="`/members/${member().id}/service-history/export`" external variant="outline" size="sm">
                                Export (Excel)
                            </BaseButton>
                        </div>
                    </div>
                    <div v-if="serviceHistory().length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Examination</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="hidden px-3 py-2 sm:table-cell">Role Performed</th>
                                    <th class="hidden px-3 py-2 md:table-cell">Attendance</th>
                                    <th class="px-3 py-2">Rating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="record in serviceHistory()" :key="record.id" class="transition-colors hover:bg-brand-50/40">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-900">{{ record.exam_title }}</p>
                                        <p class="text-xs text-slate-500">{{ record.exam_type }}</p>
                                        <p class="text-xs text-slate-400 sm:hidden">{{ record.role_label }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ record.exam_date }}</td>
                                    <td class="hidden whitespace-nowrap px-3 py-2 text-slate-600 sm:table-cell">{{ record.role_label }}</td>
                                    <td class="hidden px-3 py-2 md:table-cell">
                                        <span v-if="record.attended" class="inline-flex items-center gap-1 text-emerald-700">
                                            <AppIcon name="check-circle" class="h-4 w-4" /> Confirmed
                                        </span>
                                        <span v-else class="text-slate-400">Not confirmed</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <BaseBadge v-if="record.rating_label" :variant="record.rating_variant">
                                            {{ record.rating_label }}
                                        </BaseBadge>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="mt-4">
                        <EmptyState
                            icon="clock"
                            title="No service records yet"
                            description="Examination assignments, roles performed, and performance ratings will appear here once this member is deployed."
                        />
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="py-16 text-center text-sm text-slate-400">
            {{ error ?? 'Could not load member details.' }}
        </div>
    </BaseModal>

    <EditMemberModal
        :show="showEditModal"
        :member-id="memberId"
        @close="showEditModal = false"
        @saved="onEditSaved"
    />

    <!-- Enlarged QR -->
    <BaseModal :show="showQrModal" title="QR Code" @close="showQrModal = false">
        <div class="flex flex-col items-center gap-4">
            <QrCode v-if="raw" :value="idCard().qr_value" :size="320" />
            <p class="break-all text-center text-xs text-slate-500">{{ raw ? idCard().qr_value : '' }}</p>
        </div>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="showQrModal = false">Close</BaseButton>
            <BaseButton variant="secondary" size="sm" @click="downloadQr">Download PNG</BaseButton>
        </template>
    </BaseModal>

    <!-- Delete confirmation -->
    <BaseModal :show="showDeleteModal" title="Remove member" @close="showDeleteModal = false">
        <p class="text-sm leading-relaxed text-slate-600">
            Remove <strong>{{ member()?.name }}</strong> ({{ member()?.proctad_id }}) from the registry?
            The record is archived and the PROCTAD ID stays permanently reserved — it will never be reissued.
        </p>
        <template #footer>
            <BaseButton variant="outline" size="sm" @click="showDeleteModal = false">Cancel</BaseButton>
            <BaseButton variant="accent" size="sm" :loading="deleting" :disabled="deleting" @click="deleteMember">
                Remove Member
            </BaseButton>
        </template>
    </BaseModal>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    #print-id-card, #print-id-card * { visibility: visible; }
    #print-id-card {
        position: absolute;
        inset: 0;
        padding: 1cm;
    }
}
</style>
