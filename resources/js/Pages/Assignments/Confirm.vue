<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import TextArea from '@/Components/TextArea.vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    actionUrl: { type: String, required: true },
    responseDueBy: { type: String, default: null },
});

/* Flash messages are rendered once by PublicLayout's banner — this page must not
   repeat them, or a rejected response shows the same sentence twice. */

/** Matches the server's `max:500` on decline_reason. */
const DECLINE_REASON_MAX = 500;

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
const isConfirmed = computed(() => props.assignment.status === 'confirmed');

/**
 * Confirmed and declined must not look alike — a member who mis-clicked Decline
 * has to be able to tell at a glance, so they don't read a neutral badge as
 * "all good" and only discover the problem on exam day.
 */
const statusVariant = computed(() => ({
    confirmed: 'success',
    declined: 'accent',
    pending: 'warning',
}[props.assignment.status] ?? 'neutral'));
</script>

<template>
    <PublicLayout>
        <Head title="Confirm Assignment" />

        <section class="relative isolate flex min-h-[60vh] flex-col justify-center overflow-hidden px-4 py-16">
            <!-- CSC facade background — same treatment as HeroSection/PageHeader -->
            <div class="absolute inset-0 -z-10 bg-brand-900" aria-hidden="true">
                <img
                    :src="'/images/cscbg_facade.jpeg'"
                    alt=""
                    class="h-full w-full object-cover opacity-25"
                    aria-hidden="true"
                />
            </div>
            <!-- Dark overlay so the white card reads cleanly against the photo -->
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-900/80 via-brand-900/70 to-brand-900/85" aria-hidden="true" />

            <div class="mx-auto w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <!-- Official masthead. This page is reached from an emailed link and asks for
                     an action, which is the exact shape of a phishing page — the CSC mark is
                     here so a cautious member can see it's genuinely from the Commission. -->
                <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <img :src="'/images/csc-logo.png'" alt="Civil Service Commission" class="h-10 w-auto shrink-0" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                            Civil Service Commission — Regional Office VIII
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">PROCTAD Assignment Confirmation</p>
                    </div>
                </header>

                <div class="px-6 py-7 sm:px-8">
                    <div class="flex items-start justify-between gap-3">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900">
                            {{ alreadyResponded ? 'Your assignment' : 'Confirm your assignment' }}
                        </h1>
                        <BaseBadge :variant="statusVariant" class="mt-0.5 shrink-0">
                            {{ assignment.status_label }}
                        </BaseBadge>
                    </div>

                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        You have been designated as
                        <strong class="font-semibold text-slate-900">{{ assignment.role_label }}</strong>
                        for the examination below.
                    </p>

                    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ assignment.member_name }}</p>
                        <p class="mt-0.5 font-mono text-xs text-brand-700">{{ assignment.proctad_id }}</p>
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Examination</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ assignment.exam_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Examination Date</dt>
                            <dd class="mt-0.5 text-slate-700">{{ assignment.exam_date }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Role</dt>
                            <dd class="mt-0.5 text-slate-700">{{ assignment.role_label }}</dd>
                        </div>
                        <div v-if="assignment.venue" class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Venue</dt>
                            <!-- Venue only: test administrators are told their room in person by the
                                 secretariat on exam day, so it's deliberately withheld here. -->
                            <dd class="mt-0.5 text-slate-700">{{ assignment.venue }}</dd>
                        </div>
                    </dl>

                    <!-- Confirmed members are told their room in person on exam day, so
                         close that loop here rather than leaving them wondering. -->
                    <div v-if="isConfirmed" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-relaxed text-emerald-900">
                        <p class="font-semibold">You're confirmed for this examination.</p>
                        <p class="mt-1">
                            Your room assignment will be given to you by the secretariat when you report to the venue on
                            exam day.
                        </p>
                    </div>

                    <p v-else-if="alreadyResponded" class="mt-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-600">
                        Your response has been recorded. To change it, please contact your Field Office.
                    </p>

                    <div v-if="!alreadyResponded" class="mt-6">
                        <!-- Deadline is information, not a warning — a quiet line, so it doesn't
                             compete with the buttons for attention. -->
                        <p v-if="responseDueBy" class="border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
                            Please respond on or before
                            <strong class="font-semibold text-slate-700">{{ responseDueBy }}</strong> — this link
                            expires on that date.
                        </p>

                        <template v-if="!declining">
                            <!-- Confirming is the common path; declining is the exception that
                                 triggers re-staffing. Weight them accordingly. -->
                            <div class="mt-4">
                                <BaseButton
                                    variant="primary"
                                    size="lg"
                                    block
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                    @click="respond('confirm')"
                                >
                                    Confirm Assignment
                                </BaseButton>
                            </div>
                            <div class="mt-3 text-center">
                                <BaseButton variant="link" :disabled="form.processing" @click="respond('decline')">
                                    I'm unable to serve — decline
                                </BaseButton>
                            </div>
                        </template>

                        <template v-else>
                            <div class="mt-4">
                                <TextArea
                                    v-model="form.decline_reason"
                                    label="Reason for declining"
                                    required
                                    :maxlength="DECLINE_REASON_MAX"
                                    placeholder="Let us know why you're unable to serve so we can arrange a replacement."
                                    :error="form.errors.decline_reason"
                                />
                            </div>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row-reverse">
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
                                <BaseButton variant="outline" size="lg" block :disabled="form.processing" @click="declining = false">
                                    Back
                                </BaseButton>
                            </div>
                        </template>
                    </div>

                    <p class="mt-8 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-400">
                        If you did not expect this assignment, please contact your Field Office.
                    </p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
