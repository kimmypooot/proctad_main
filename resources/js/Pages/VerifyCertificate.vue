<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    result: { type: Object, default: null },
    code: { type: String, required: true },
    verifiedAt: { type: String, default: null },
});
</script>

<template>
    <PublicLayout>
        <Head title="Verify Certificate" />

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
                <!-- Official masthead. This page exists to answer "is this document genuine?",
                     so the CSC mark carries real weight — it's what tells the person holding
                     the certificate that the answer itself comes from the Commission. -->
                <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <img :src="'/images/csc-logo.png'" alt="Civil Service Commission" class="h-10 w-auto shrink-0" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                            Civil Service Commission — Regional Office VIII
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">PROCTAD Certificate Verification</p>
                    </div>
                </header>

                <div class="px-6 py-7 sm:px-8">
                    <template v-if="result">
                        <div class="text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <AppIcon name="check-badge" class="h-8 w-8" />
                            </span>
                            <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Authentic Certificate</h1>
                            <p class="mt-1 text-sm font-semibold text-brand-700">{{ result.type_label }}</p>

                            <!-- The certificate number is what gets matched against the paper in
                                 hand, so it reads as a chip rather than fine print. -->
                            <p class="mt-3 inline-flex rounded-md bg-slate-100 px-3 py-1 font-mono text-xs font-medium text-slate-700">
                                {{ result.certificate_no }}
                            </p>
                        </div>

                        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Issued To</p>
                            <p class="mt-0.5 text-base font-semibold text-slate-900">{{ result.member_name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-brand-700">{{ result.proctad_id }}</p>
                        </div>

                        <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            <div v-if="result.source" class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">For</dt>
                                <dd class="mt-0.5 text-slate-700">
                                    {{ result.source }}
                                    <span v-if="result.source_date" class="text-slate-400"> &middot; {{ result.source_date }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</dt>
                                <dd class="mt-0.5 text-slate-700">{{ result.field_office ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Date Issued</dt>
                                <dd class="mt-0.5 text-slate-700">{{ result.released_at ?? '—' }}</dd>
                            </div>
                        </dl>

                        <p v-if="verifiedAt" class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">
                            Verified <strong class="font-semibold text-slate-700">{{ verifiedAt }}</strong>
                        </p>

                        <p class="mt-3 text-xs leading-relaxed text-slate-400">
                            If the details above do not match the presented certificate, report it to CSC Regional
                            Office VIII.
                        </p>
                    </template>

                    <template v-else>
                        <div class="text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600">
                                <AppIcon name="x-circle" class="h-8 w-8" />
                            </span>
                            <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Certificate Not Found</h1>

                            <p class="mt-3 inline-flex rounded-md bg-slate-100 px-3 py-1 font-mono text-xs font-medium text-slate-700">
                                {{ code }}
                            </p>

                            <p class="mt-4 text-sm leading-relaxed text-slate-500">
                                This number does not match any released certificate in the PROCTAD system. It may be
                                unreleased, revoked, mistyped, or counterfeit.
                            </p>
                        </div>

                        <p class="mt-6 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-400">
                            Check the number against the printed certificate and try again. If it still does not match,
                            report it to CSC Regional Office VIII.
                        </p>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
