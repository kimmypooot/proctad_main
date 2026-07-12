<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import TextArea from '@/Components/TextArea.vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    actionUrl: { type: String, required: true },
});

const flashStatus = computed(() => usePage().props.flash?.status);
const flashError = computed(() => usePage().props.flash?.error);

const declining = ref(false);
const form = useForm({ action: 'confirm', decline_reason: '' });

const respond = (action) => {
    if (action === 'decline' && !declining.value) {
        declining.value = true;
        return;
    }

    form.action = action;
    form.post(props.actionUrl);
};

const alreadyResponded = computed(() => props.assignment.status !== 'pending');
</script>

<template>
    <PublicLayout>
        <Head title="Confirm Assignment" />

        <section class="mx-auto flex min-h-[60vh] max-w-lg flex-col justify-center px-4 py-16">
            <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">PROCTAD Assignment Confirmation</p>
                <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900">{{ assignment.exam_name }}</h1>

                <dl class="mt-6 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Member</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ assignment.member_name }}</dd>
                        <dd class="font-mono text-xs text-brand-700">{{ assignment.proctad_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Examination Date</dt>
                        <dd class="mt-0.5 text-slate-700">{{ assignment.exam_date }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Role</dt>
                        <dd class="mt-0.5 text-slate-700">{{ assignment.role_label }}</dd>
                    </div>
                    <div v-if="assignment.venue">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Venue</dt>
                        <dd class="mt-0.5 text-slate-700">
                            {{ assignment.venue }}<span v-if="assignment.room"> — Room {{ assignment.room }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Status</dt>
                        <dd class="mt-1.5">
                            <BaseBadge :variant="alreadyResponded ? 'neutral' : 'warning'">
                                {{ assignment.status_label }}
                            </BaseBadge>
                        </dd>
                    </div>
                </dl>

                <BaseAlert v-if="flashStatus" class="mt-6" variant="success">
                    {{ flashStatus }}
                </BaseAlert>

                <BaseAlert v-if="flashError" class="mt-6" variant="error">
                    {{ flashError }}
                </BaseAlert>

                <div v-if="!alreadyResponded && !flashStatus" class="mt-8">
                    <template v-if="!declining">
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <BaseButton
                                variant="primary"
                                size="lg"
                                block
                                :loading="form.processing && form.action === 'confirm'"
                                :disabled="form.processing"
                                @click="respond('confirm')"
                            >
                                Confirm Assignment
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="lg"
                                block
                                :disabled="form.processing"
                                @click="respond('decline')"
                            >
                                Decline Assignment
                            </BaseButton>
                        </div>
                    </template>

                    <template v-else>
                        <TextArea
                            v-model="form.decline_reason"
                            label="Reason for declining"
                            required
                            placeholder="Let us know why you're unable to serve so we can arrange a replacement."
                            :error="form.errors.decline_reason"
                        />
                        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <BaseButton variant="outline" size="lg" block :disabled="form.processing" @click="declining = false">
                                Back
                            </BaseButton>
                            <BaseButton
                                variant="accent"
                                size="lg"
                                block
                                :loading="form.processing"
                                :disabled="form.processing"
                                @click="respond('decline')"
                            >
                                Submit Decline
                            </BaseButton>
                        </div>
                    </template>
                </div>

                <p class="mt-8 text-xs leading-relaxed text-slate-400">
                    Civil Service Commission Regional Office VIII — PROCTAD Management System.
                    If you did not expect this assignment, please contact your Testing Center.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
