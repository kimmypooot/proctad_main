<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/StatCard.vue';
import TextArea from '@/Components/TextArea.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    records: { type: Array, required: true },
});

const awaitingCount = computed(() => props.records.filter((r) => r.awaiting_response).length);

/* --- Respond (confirm / decline) --- */
const declining = ref(null);
const form = useForm({ action: 'confirm', decline_reason: '' });

const confirm = (record) => {
    form.action = 'confirm';
    form.decline_reason = '';
    form.post(`/my/assignments/${record.id}/respond`, { preserveScroll: true });
};

const openDecline = (record) => {
    form.clearErrors();
    form.action = 'decline';
    form.decline_reason = '';
    declining.value = record;
};

const submitDecline = () => {
    form.post(`/my/assignments/${declining.value.id}/respond`, {
        preserveScroll: true,
        onSuccess: () => (declining.value = null),
    });
};
</script>

<template>
    <Head title="My Assignments" />

    <DashboardLayout>
        <DashboardPageHeader
            title="My Assignments"
            subtitle="Examinations you have been deployed to, and your response."
        />

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="clipboard-check"
                title="No PROCTAD record linked to your account"
                description="Your assignments appear here once your Field Office registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="records.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <StatCard compact label="Upcoming Assignments" :value="records.length" icon="calendar" />
                <StatCard
                    compact
                    label="Awaiting Your Response"
                    :value="awaitingCount"
                    icon="exclamation-triangle"
                    :accent="awaitingCount ? 'amber' : 'slate'"
                />
            </div>

            <ul class="mt-6 space-y-4">
                <li
                    v-for="record in records"
                    :key="record.id"
                    class="rounded-xl border bg-white p-5"
                    :class="record.awaiting_response ? 'border-amber-300' : 'border-slate-200'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-base font-semibold text-slate-900">{{ record.exam_title }}</h3>
                            <p class="mt-0.5 text-sm text-slate-500">{{ record.exam_date }}</p>
                        </div>
                        <BaseBadge :variant="record.status_variant">{{ record.status_label }}</BaseBadge>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Role</dt>
                            <dd class="mt-0.5 break-words text-slate-700">{{ record.role_label }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Venue</dt>
                            <dd class="mt-0.5 break-words text-slate-700">{{ record.venue ?? 'To be announced' }}</dd>
                        </div>
                    </dl>

                    <!-- Room is disclosed in person on exam day, so it is never sent to this page. -->
                    <p class="mt-3 flex items-start gap-1.5 text-xs text-slate-400">
                        <AppIcon name="information-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Your assigned room is given to you by the secretariat on examination day.
                    </p>

                    <div v-if="record.awaiting_response" class="mt-4 border-t border-slate-100 pt-4">
                        <p v-if="record.respond_by" class="text-sm text-amber-700">
                            Please respond by <strong>{{ record.respond_by }}</strong>.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <BaseButton
                                variant="primary"
                                size="sm"
                                :disabled="form.processing"
                                @click="confirm(record)"
                            >
                                Confirm Attendance
                            </BaseButton>
                            <BaseButton variant="outline" size="sm" :disabled="form.processing" @click="openDecline(record)">
                                Decline
                            </BaseButton>
                        </div>
                    </div>

                    <div v-else class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-500">
                        <p v-if="record.responded_at">You responded on {{ record.responded_at }}.</p>
                        <p v-if="record.decline_reason" class="mt-1">
                            <span class="font-medium text-slate-600">Reason given:</span> {{ record.decline_reason }}
                        </p>
                        <p class="mt-1">
                            To change your response, please contact your Field Office.
                        </p>
                    </div>
                </li>
            </ul>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="calendar"
                title="No upcoming assignments"
                description="When your Field Office deploys you to an examination, it will appear here and you'll be emailed a confirmation request."
            />
        </div>
    </DashboardLayout>

    <!-- Decline, with a reason -->
    <BaseModal :show="declining !== null" title="Decline this assignment" @close="declining = null">
        <p class="text-sm leading-relaxed text-slate-600">
            You are declining <strong>{{ declining?.exam_title }}</strong> on {{ declining?.exam_date }}.
            Your Field Office will be notified so the slot can be re-staffed.
        </p>

        <BaseAlert variant="warning" class="mt-4">
            This cannot be undone here — changing your response afterwards means contacting your Field Office.
        </BaseAlert>

        <div class="mt-4">
            <TextArea
                v-model="form.decline_reason"
                label="Reason"
                required
                :rows="3"
                :maxlength="500"
                placeholder="Let your Field Office know why you can't serve."
                :error="form.errors.decline_reason"
            />
        </div>

        <template #footer>
            <BaseButton variant="outline" size="sm" :disabled="form.processing" @click="declining = null">
                Cancel
            </BaseButton>
            <BaseButton
                variant="accent"
                size="sm"
                :loading="form.processing"
                :disabled="form.processing"
                @click="submitDecline"
            >
                Decline Assignment
            </BaseButton>
        </template>
    </BaseModal>
</template>
