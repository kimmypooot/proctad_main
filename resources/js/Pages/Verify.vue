<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';

defineProps({
    result: { type: Object, default: null },
    code: { type: String, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head title="Verify PROCTAD ID" />

        <section class="mx-auto flex min-h-[60vh] max-w-lg flex-col justify-center px-4 py-16">
            <div v-if="result" class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <AppIcon name="check-badge" class="h-8 w-8" />
                </span>
                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Verified PROCTAD Member</h1>
                <p class="mt-1 font-mono text-sm font-semibold text-brand-700">{{ result.proctad_id }}</p>

                <dl class="mt-6 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Name</dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-900">{{ result.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</dt>
                        <dd class="mt-0.5 text-slate-700">{{ result.field_office }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Accreditation Status</dt>
                        <dd class="mt-1.5 flex justify-center">
                            <BaseBadge :variant="result.status_variant">{{ result.status_label }}</BaseBadge>
                        </dd>
                    </div>
                </dl>

                <p class="mt-8 text-xs leading-relaxed text-slate-400">
                    This verification is issued by the Civil Service Commission Regional Office VIII
                    PROCTAD system. If the details do not match the presented ID, report it to CSC RO VIII.
                </p>
            </div>

            <div v-else class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <AppIcon name="x-circle" class="h-8 w-8" />
                </span>
                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">Invalid PROCTAD ID</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                    The code <span class="font-mono font-semibold text-slate-700">{{ code }}</span> does not match
                    any record in the PROCTAD registry. The ID may be revoked, mistyped, or counterfeit.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
