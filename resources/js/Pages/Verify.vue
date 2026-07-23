<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';

const props = defineProps({
    result: { type: Object, default: null },
    code: { type: String, required: true },
    verifiedAt: { type: String, default: null },
});

/**
 * The headline has to reflect the member's accreditation status, not merely
 * whether the ID exists. A disqualified member's ID still resolves — showing a
 * green check and "Verified PROCTAD Member" over it would tell someone checking
 * an ID at a venue exactly the wrong thing at a glance.
 */
const outcome = computed(() => {
    if (!props.result) {
        return {
            tone: 'red',
            icon: 'x-circle',
            title: 'Invalid PROCTAD ID',
        };
    }

    return {
        active: {
            tone: 'emerald',
            icon: 'check-badge',
            title: 'Verified PROCTAD Member',
        },
        inactive: {
            tone: 'amber',
            icon: 'exclamation-triangle',
            title: 'Member Not Active',
            note: 'This ID matches a registered member, but they are not currently active and should not be serving as a test administrator.',
        },
        disqualified: {
            tone: 'red',
            icon: 'exclamation-triangle',
            title: 'Member Disqualified',
            note: 'This ID matches a registered member who has been disqualified. They must not be allowed to serve as a test administrator.',
        },
    }[props.result.status] ?? {
        tone: 'amber',
        icon: 'exclamation-triangle',
        title: 'Accreditation Unclear',
    };
});

const TONES = {
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    red: 'bg-red-50 text-red-600',
};

const NOTE_TONES = {
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    amber: 'border-amber-200 bg-amber-50 text-amber-900',
    red: 'border-red-200 bg-red-50 text-red-900',
};
</script>

<template>
    <PublicLayout>
        <Head title="Verify PROCTAD ID" />

        <section class="relative isolate flex min-h-[60vh] flex-col justify-center overflow-hidden px-4 py-16">
            <!-- CSC facade background — same treatment as Assignments/Confirm. -->
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
                <!-- Official masthead. This page answers "is this person accredited?" for
                     someone holding an ID, so the CSC mark is what tells them the answer
                     itself comes from the Commission. -->
                <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <img :src="'/images/csc-logo.png'" alt="Civil Service Commission" class="h-10 w-auto shrink-0" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                            Civil Service Commission — Regional Office VIII
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">PROCTAD ID Verification</p>
                    </div>
                </header>

                <div class="px-6 py-7 sm:px-8">
                    <div class="text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full" :class="TONES[outcome.tone]">
                            <AppIcon :name="outcome.icon" class="h-8 w-8" />
                        </span>
                        <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">{{ outcome.title }}</h1>

                        <!-- The ID is what gets matched against the card in hand, so it reads
                             as a chip rather than fine print. -->
                        <p class="mt-3 inline-flex rounded-md bg-slate-100 px-3 py-1 font-mono text-xs font-medium text-slate-700">
                            {{ result ? result.proctad_id : code }}
                        </p>
                    </div>

                    <template v-if="result">
                        <p
                            v-if="outcome.note"
                            class="mt-5 rounded-lg border px-4 py-3 text-sm leading-relaxed"
                            :class="NOTE_TONES[outcome.tone]"
                        >
                            {{ outcome.note }}
                        </p>

                        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Name</p>
                            <p class="mt-0.5 text-base font-semibold text-slate-900">{{ result.name }}</p>
                        </div>

                        <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Field Office</dt>
                                <dd class="mt-0.5 text-slate-700">{{ result.field_office ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Accreditation Status</dt>
                                <dd class="mt-1">
                                    <BaseBadge :variant="result.status_variant">{{ result.status_label }}</BaseBadge>
                                </dd>
                            </div>
                        </dl>

                        <p v-if="verifiedAt" class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            Verified <strong class="font-semibold text-slate-700">{{ verifiedAt }}</strong>
                        </p>

                        <p class="mt-3 text-xs leading-relaxed text-slate-400">
                            If the details above do not match the presented ID, report it to CSC Regional Office VIII.
                        </p>
                    </template>

                    <template v-else>
                        <p class="mt-4 text-center text-sm leading-relaxed text-slate-500">
                            This code does not match any record in the PROCTAD registry. The ID may be revoked,
                            mistyped, or counterfeit.
                        </p>

                        <p class="mt-6 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-400">
                            Check the code against the printed ID and try again. If it still does not match, report it
                            to CSC Regional Office VIII.
                        </p>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
