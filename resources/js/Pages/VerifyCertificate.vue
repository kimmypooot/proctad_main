<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    result: { type: Object, default: null },
    code: { type: String, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head title="Verify Certificate" />

        <section class="mx-auto flex min-h-[60vh] max-w-lg flex-col justify-center px-4 py-16">
            <div v-if="result" class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <AppIcon name="check-badge" class="h-8 w-8" />
                </span>
                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Authentic Certificate</h1>
                <p class="mt-1 text-sm font-semibold text-brand-700">{{ result.type_label }}</p>
                <p class="mt-0.5 font-mono text-xs text-slate-400">{{ result.certificate_no }}</p>

                <dl class="mt-6 space-y-3 text-left text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Issued To</dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-900">{{ result.member_name }}</dd>
                        <dd class="font-mono text-xs text-brand-700">{{ result.proctad_id }}</dd>
                    </div>
                    <div v-if="result.source">
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

                <p class="mt-8 text-xs leading-relaxed text-slate-400">
                    This certificate was issued and released by the Civil Service Commission Regional Office VIII
                    PROCTAD system. If the details do not match the presented certificate, report it to CSC RO VIII.
                </p>
            </div>

            <div v-else class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <AppIcon name="x-circle" class="h-8 w-8" />
                </span>
                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Certificate Not Found</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                    The certificate number <span class="font-mono font-semibold text-slate-700">{{ code }}</span> does not
                    match any released certificate in the PROCTAD system. It may be unreleased, revoked, mistyped, or counterfeit.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
