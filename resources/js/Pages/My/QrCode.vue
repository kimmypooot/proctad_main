<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MemberIdCard from '@/Components/MemberIdCard.vue';

defineProps({
    idCard: { type: Object, default: null },
});

const printCard = () => window.print();
</script>

<template>
    <Head title="My QR Code" />

    <DashboardLayout>
        <DashboardPageHeader
            class="print:hidden"
            title="My QR Code & Digital ID"
            subtitle="Present this QR code for attendance at trainings, briefings, and examinations."
        >
            <template v-if="idCard" #actions>
                <BaseButton variant="outline" size="sm" @click="printCard">
                    <AppIcon name="newspaper" class="h-4 w-4" />
                    Print
                </BaseButton>
                <BaseButton variant="primary" size="sm" href="/my/id-card/download" external>
                    <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                    Download ID (PDF)
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="!idCard" class="mt-6 print:hidden">
            <EmptyState
                icon="qr-code"
                title="No PROCTAD record linked to your account"
                description="Your QR code and digital ID become available once your Testing Center registers you in the PROCTAD registry."
            />
        </div>

        <div v-else class="mt-8 grid gap-8 lg:grid-cols-5">
            <!-- ID card -->
            <div id="print-id-card" class="lg:col-span-2">
                <div class="rounded-2xl border border-slate-200 bg-gradient-to-b from-brand-50 to-white p-8 print:border-0 print:bg-none print:p-0">
                    <MemberIdCard :card="idCard" />
                </div>
            </div>

            <!-- Details -->
            <div class="space-y-6 print:hidden lg:col-span-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xl font-bold text-slate-900">{{ idCard.name }}</p>
                            <p class="mt-0.5 font-mono text-sm font-semibold text-brand-700">{{ idCard.proctad_id }}</p>
                        </div>
                        <BaseBadge :variant="idCard.status === 'active' ? 'success' : 'warning'">
                            {{ idCard.status_label }}
                        </BaseBadge>
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div v-if="idCard.position" class="flex items-start gap-2.5">
                            <AppIcon name="briefcase" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Position</dt>
                                <dd class="mt-0.5 truncate text-sm text-slate-700">{{ idCard.position }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <AppIcon name="building-office" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Agency</dt>
                                <dd class="mt-0.5 truncate text-sm text-slate-700">{{ idCard.agency }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <AppIcon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</dt>
                                <dd class="mt-0.5 truncate text-sm text-slate-700">{{ idCard.field_office }}</dd>
                            </div>
                        </div>
                        <div v-if="idCard.signatory" class="flex items-start gap-2.5">
                            <AppIcon name="check-badge" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Certified By</dt>
                                <dd class="mt-0.5 truncate text-sm text-slate-700">
                                    {{ idCard.signatory.name }}
                                    <span class="text-slate-400">— {{ idCard.signatory.position }}</span>
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                        <AppIcon name="light-bulb" class="h-5 w-5 text-brand-600" />
                        How to use your Digital ID
                    </h2>
                    <ul class="mt-4 space-y-3">
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">1</span>
                            <p class="text-sm text-slate-600">Tap or click the card to flip between front and back.</p>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">2</span>
                            <p class="text-sm text-slate-600">Present the QR code to a scanner for attendance at trainings, briefings, and examinations.</p>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">3</span>
                            <p class="text-sm text-slate-600">Download the PDF for a printable, wallet-sized front-and-back copy of your official ID.</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
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
}
</style>
