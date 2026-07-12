<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    hasRecord: { type: Boolean, required: true },
    certificates: { type: Array, required: true },
});

const releasedCount = computed(() => props.certificates.filter((c) => c.status === 'released').length);
const pendingCount = computed(() => props.certificates.filter((c) => c.status === 'pending').length);

const typeIcons = {
    appearance: 'check-badge',
    appreciation: 'trophy',
    completion: 'academic-cap',
    designation_order: 'document-text',
};

const iconFor = (certificate) => typeIcons[certificate.type] ?? 'document-check';
</script>

<template>
    <Head title="My Certificates" />

    <DashboardLayout>
        <DashboardPageHeader title="My Certificates" subtitle="Certificates and Designation Orders issued to you." />

        <div v-if="!hasRecord" class="mt-6">
            <EmptyState
                icon="document-check"
                title="No PROCTAD record linked to your account"
                description="Your certificates become available once your Testing Center registers you in the PROCTAD registry."
            />
        </div>

        <template v-else-if="certificates.length">
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard compact label="Total Certificates" :value="certificates.length" icon="document-check" />
                <StatCard compact label="Released" :value="releasedCount" icon="check-badge" />
                <StatCard compact label="Pending Approval" :value="pendingCount" icon="clock" />
            </div>

            <div class="mt-6 space-y-3">
                <div
                    v-for="certificate in certificates"
                    :key="certificate.id"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <AppIcon :name="iconFor(certificate)" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ certificate.type_label }}</p>
                            <p class="mt-1 font-mono text-sm font-semibold text-slate-900">{{ certificate.certificate_no ?? '— pending —' }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ certificate.source }}</p>
                            <p v-if="certificate.source_date" class="text-xs text-slate-400">{{ certificate.source_date }}</p>
                            <p v-if="certificate.disapproval_remarks" class="mt-1 max-w-md text-xs text-accent-600">
                                {{ certificate.disapproval_remarks }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <BaseBadge :variant="certificate.status_variant">{{ certificate.status_label }}</BaseBadge>
                        <BaseButton
                            v-if="certificate.status === 'released'"
                            variant="link"
                            external
                            :href="`/certificates/${certificate.id}/download`"
                        >
                            <AppIcon name="document-check" class="h-4 w-4" />
                            Download
                        </BaseButton>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="mt-6">
            <EmptyState
                icon="document-check"
                title="No certificates yet"
                description="Certificates you earn from examinations and trainings will appear here."
            />
        </div>
    </DashboardLayout>
</template>
