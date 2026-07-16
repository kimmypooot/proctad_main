<script setup>
import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EditMemberModal from './EditMemberModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import MemberIdCard from '@/Components/MemberIdCard.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    memberId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const raw = ref(null);

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

watch(() => props.show, (open) => {
    if (open && props.memberId) {
        fetchMember();
    }
});

const fetchMember = async () => {
    loading.value = true;
    raw.value = null;
    try {
        const response = await fetch(`/members/${props.memberId}/details`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error();
        const json = await response.json();
        raw.value = json;

        for (const key of Object.keys(rows)) {
            delete rows[key];
        }
        for (const req of json.requirements) {
            rows[req.key] = {
                complied: req.complied,
                remarks: req.remarks ?? '',
                file: null,
                saving: false,
            };
        }
    } catch {
        raw.value = null;
    } finally {
        loading.value = false;
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

const detailItems = (m) => [
    ['Sex', m.sex === 'male' ? 'Male' : 'Female'],
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

            <!-- 3-column grid skeleton -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
                    <div class="h-5 w-32 rounded bg-slate-200" />
                    <div class="space-y-3">
                        <div v-for="i in 6" :key="i" class="flex items-baseline justify-between gap-4">
                            <div class="h-3 w-16 rounded bg-slate-200" />
                            <div class="h-4 w-28 rounded bg-slate-200" />
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="h-5 w-44 rounded bg-slate-200" />
                        <div class="h-5 w-20 rounded-full bg-slate-200" />
                    </div>
                    <div v-for="i in 3" :key="i" class="space-y-3 pt-4 border-t border-slate-100">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 h-6 w-6 rounded-full bg-slate-200" />
                            <div class="flex-1 space-y-1.5">
                                <div class="h-4 w-44 rounded bg-slate-200" />
                                <div class="h-3 w-28 rounded bg-slate-200" />
                            </div>
                        </div>
                        <div class="ml-9 grid gap-3 sm:grid-cols-[auto_1fr_auto_auto]">
                            <div class="h-4 w-16 rounded bg-slate-200" />
                            <div class="h-8 rounded-lg bg-slate-200" />
                            <div class="h-8 w-24 rounded-lg bg-slate-200" />
                            <div class="h-8 w-14 rounded-lg bg-slate-200" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Digital ID skeleton -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="h-5 w-24 rounded bg-slate-200" />
                    <div class="h-8 w-28 rounded-lg bg-slate-200" />
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-8 flex items-center justify-center">
                    <LoadingSpinner class="h-8 w-8 text-slate-300" />
                </div>
            </div>

            <!-- Service History skeleton -->
            <div class="rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <div class="h-5 w-28 rounded bg-slate-200" />
                    <div class="flex gap-2">
                        <div class="h-8 w-16 rounded-lg bg-slate-200" />
                        <div class="h-8 w-28 rounded-lg bg-slate-200" />
                    </div>
                </div>
                <div class="p-5 space-y-3">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-4">
                        <div class="h-4 flex-1 rounded bg-slate-200" />
                        <div class="h-4 w-24 rounded bg-slate-200" />
                        <div class="h-4 w-20 rounded bg-slate-200 hidden sm:block" />
                        <div class="h-4 w-24 rounded bg-slate-200 hidden md:block" />
                        <div class="h-5 w-16 rounded-full bg-slate-200" />
                    </div>
                </div>
            </div>
        </div>

        <template v-else-if="raw">
            <div class="max-h-[75vh] space-y-6 overflow-y-auto -mx-6 -mt-5 px-6 pt-5">
                <!-- Header with photo -->
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
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
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-brand-700">{{ member().proctad_id }}</p>
                            <h3 class="text-xl font-semibold text-slate-900">{{ member().name }}</h3>
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

                <!-- Member Details + Eligibility Requirements -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-6">
                        <h4 class="text-base font-semibold text-slate-900">Member Details</h4>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div v-for="[label, value] in detailItems(member())" :key="label">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</dt>
                                <dd class="mt-0.5 text-slate-700">{{ value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
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
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">{{ req.label }}</p>
                                            <p v-if="!can().update && req.remarks" class="mt-0.5 text-xs text-slate-500">
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
                                    <label class="cursor-pointer text-xs font-medium text-slate-500 hover:text-brand-700">
                                        <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" @change="onFileChange(req, $event)">
                                        <span class="inline-flex items-center gap-1">
                                            <AppIcon name="cloud-arrow-up" class="h-4 w-4" />
                                            {{ rows[req.key].file ? rows[req.key].file.name : 'Attach file' }}
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
                </div>

                <!-- Digital ID -->
                <div>
                    <div class="flex items-center justify-between print:hidden">
                        <h4 class="text-lg font-semibold text-slate-900">Digital ID</h4>
                        <BaseButton variant="outline" size="sm" @click="printCard">Print ID Card</BaseButton>
                    </div>
                    <div id="print-id-card" class="mt-4">
                        <MemberIdCard :card="idCard()" />
                    </div>
                </div>

                <!-- Service History -->
                <div>
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
            Could not load member details.
        </div>
    </BaseModal>

    <EditMemberModal
        :show="showEditModal"
        :member-id="memberId"
        @close="showEditModal = false"
        @saved="onEditSaved"
    />

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
