<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MemberIdCard from '@/Components/MemberIdCard.vue';

const props = defineProps({
    member: { type: Object, required: true },
    requirements: { type: Array, required: true },
    compliedCount: { type: Number, required: true },
    serviceHistory: { type: Array, required: true },
    idCard: { type: Object, required: true },
    can: { type: Object, required: true },
});

const printCard = () => window.print();

const showDeleteModal = ref(false);
const deleting = ref(false);

const deleteMember = () => {
    deleting.value = true;
    router.delete(`/members/${props.member.id}`, {
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

/** One lightweight editable state per requirement row. */
const rows = reactive(Object.fromEntries(props.requirements.map((req) => [req.key, {
    complied: req.complied,
    remarks: req.remarks ?? '',
    file: null,
    saving: false,
}])));

const saveRequirement = (req) => {
    const row = rows[req.key];
    row.saving = true;
    router.post(`/members/${props.member.id}/requirements/${req.key}`, {
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

const detailItems = () => [
    ['Sex', props.member.sex === 'male' ? 'Male' : 'Female'],
    ['Email', props.member.email],
    ['Mobile Number', props.member.mobile_number],
    ['Agency', props.member.agency],
    ['Position', props.member.position ?? '—'],
    ['Registered', props.member.created_at],
];
</script>

<template>
    <Head :title="member.proctad_id" />

    <DashboardLayout>
        <DashboardPageHeader :title="member.name">
            <template #eyebrow>{{ member.proctad_id }}</template>
            <template #badges>
                <BaseBadge :variant="member.status_variant">{{ member.status_label }}</BaseBadge>
                <BaseBadge v-if="member.field_office" variant="neutral">
                    <AppIcon name="building-office" class="h-3.5 w-3.5" />
                    {{ member.field_office.name }}
                </BaseBadge>
            </template>
            <template v-if="can.update" #actions>
                <BaseButton :href="`/members/${member.id}/edit`" variant="outline" size="sm">Edit</BaseButton>
                <BaseButton variant="ghost" size="sm" @click="showDeleteModal = true">
                    <span class="text-accent-600">Remove</span>
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div
            v-if="member.status === 'disqualified' && member.disqualification_remarks"
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
            <strong>Disqualification remarks:</strong> {{ member.disqualification_remarks }}
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Profile details -->
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">Member Details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div v-for="[label, value] in detailItems()" :key="label">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</dt>
                        <dd class="mt-0.5 text-slate-700">{{ value }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Eligibility checklist -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">Eligibility Requirements</h2>
                    <BaseBadge :variant="compliedCount === requirements.length ? 'success' : 'warning'">
                        {{ compliedCount }} / {{ requirements.length }} complied
                    </BaseBadge>
                </div>

                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="req in requirements" :key="req.key" class="py-4">
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
                                    <p v-if="!can.update && req.remarks" class="mt-0.5 text-xs text-slate-500">
                                        {{ req.remarks }}
                                    </p>
                                </div>
                            </div>
                            <a
                                v-if="req.has_file"
                                :href="`/members/${member.id}/requirements/${req.key}/download`"
                                class="inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"
                            >
                                <AppIcon name="document-text" class="h-4 w-4" />
                                Download file
                            </a>
                        </div>

                        <!-- Editable controls -->
                        <div v-if="can.update" class="mt-3 grid gap-3 pl-9 sm:grid-cols-[auto_1fr_auto_auto] sm:items-center">
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
        <div class="mt-6">
            <div class="flex items-center justify-between print:hidden">
                <h2 class="text-lg font-semibold text-slate-900">Digital ID</h2>
                <BaseButton variant="outline" size="sm" @click="printCard">Print ID Card</BaseButton>
            </div>
            <div id="print-id-card" class="mt-4">
                <MemberIdCard :card="idCard" />
            </div>
        </div>

        <!-- Service history -->
        <div class="mt-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Service History</h2>
                <div class="flex gap-2">
                    <BaseButton :href="`/members/${member.id}/service-history/print`" external target="_blank" variant="outline" size="sm">
                        Print
                    </BaseButton>
                    <BaseButton :href="`/members/${member.id}/service-history/export`" external variant="outline" size="sm">
                        Export (Excel)
                    </BaseButton>
                </div>
            </div>
            <div v-if="serviceHistory.length" class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
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
                        <tr v-for="record in serviceHistory" :key="record.id" class="transition-colors hover:bg-brand-50/40">
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

        <!-- Delete confirmation -->
        <BaseModal :show="showDeleteModal" title="Remove member" @close="showDeleteModal = false">
            <p class="text-sm leading-relaxed text-slate-600">
                Remove <strong>{{ member.name }}</strong> ({{ member.proctad_id }}) from the registry?
                The record is archived and the PROCTAD ID stays permanently reserved — it will never be reissued.
            </p>
            <template #footer>
                <BaseButton variant="outline" size="sm" @click="showDeleteModal = false">Cancel</BaseButton>
                <BaseButton variant="accent" size="sm" :loading="deleting" :disabled="deleting" @click="deleteMember">
                    Remove Member
                </BaseButton>
            </template>
        </BaseModal>
    </DashboardLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #print-id-card,
    #print-id-card * {
        visibility: visible;
    }
    #print-id-card {
        position: absolute;
        inset: 0;
        padding: 1cm;
    }
}
</style>
