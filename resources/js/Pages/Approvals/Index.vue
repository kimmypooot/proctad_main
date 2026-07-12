<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TextArea from '@/Components/TextArea.vue';

defineProps({
    pending: { type: Array, required: true },
});

const approveForm = useForm({});
const approve = (certificate) => approveForm.post(`/certificates/${certificate.id}/approve`, { preserveScroll: true });

const disapproving = ref(null);
const disapproveForm = useForm({ remarks: '' });

const openDisapprove = (certificate) => {
    disapproving.value = certificate;
    disapproveForm.reset();
    disapproveForm.clearErrors();
};

const confirmDisapprove = () => disapproveForm.post(`/certificates/${disapproving.value.id}/disapprove`, {
    preserveScroll: true,
    onSuccess: () => (disapproving.value = null),
});
</script>

<template>
    <Head title="Approvals" />

    <DashboardLayout>
        <DashboardPageHeader
            title="Pending Approvals"
            subtitle="Certificate and Designation Order requests awaiting your decision."
        />

        <div v-if="pending.length" class="mt-6 space-y-4">
            <div
                v-for="certificate in pending"
                :key="certificate.id"
                class="rounded-xl border border-slate-200 bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ certificate.type_label }}</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ certificate.member.name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ certificate.member.proctad_id }}</p>
                    </div>
                    <div class="flex gap-2">
                        <BaseButton variant="outline" size="sm" @click="openDisapprove(certificate)">
                            Disapprove
                        </BaseButton>
                        <BaseButton
                            variant="primary"
                            size="sm"
                            :loading="approveForm.processing"
                            :disabled="approveForm.processing"
                            @click="approve(certificate)"
                        >
                            <AppIcon name="check-circle" class="h-4 w-4" />
                            Approve & Release
                        </BaseButton>
                    </div>
                </div>

                <dl class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</dt>
                        <dd class="mt-0.5 text-slate-700">{{ certificate.field_office ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Source</dt>
                        <dd class="mt-0.5 text-slate-700">{{ certificate.source }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Date</dt>
                        <dd class="mt-0.5 text-slate-700">{{ certificate.source_date ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Requested</dt>
                        <dd class="mt-0.5 text-slate-700">{{ certificate.requested_by }} · {{ certificate.requested_at }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div v-else class="mt-6">
            <EmptyState
                icon="clipboard-check"
                title="Nothing pending"
                description="You're all caught up — no approval requests waiting on your decision."
            />
        </div>

        <!-- Disapprove modal -->
        <BaseModal :show="!!disapproving" title="Disapprove request" @close="disapproving = null">
            <p class="text-sm leading-relaxed text-slate-600">
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
    </DashboardLayout>
</template>
