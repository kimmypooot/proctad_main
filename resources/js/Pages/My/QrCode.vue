<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MemberIdCard from '@/Components/MemberIdCard.vue';
import QrCode from '@/Components/QrCode.vue';

defineProps({
    idCard: { type: Object, default: null },
});

const printCard = () => window.print();

/**
 * Scan mode.
 *
 * This page tells members to "present this QR code for attendance", but the only
 * code on it was the 130px one inside the ID card artwork — small, sat on a
 * background image, beside a photo. At a venue gate, on a phone that has
 * auto-dimmed in daylight, that is the difference between scanning first time
 * and holding up a queue.
 *
 * Full screen, plain white, as large as the viewport allows. The PROCTAD ID is
 * shown underneath in large text so an operator can key it in when scanning
 * fails, which is the fallback that actually gets used.
 *
 * Rendered with the same branding as the ID card, deliberately. An unbranded
 * code scans marginally better — lower error correction, no logo occluding the
 * centre — but it produces a visibly different pattern for the same value, and a
 * member who notices their two codes look different stops trusting both. At a
 * gate, hesitation costs more than the scan-rate difference.
 */
const scanMode = ref(false);

// The code is sized from the viewport, so it has to survive a rotation.
const windowWidth = ref(window.innerWidth);
const windowHeight = ref(window.innerHeight);
const onResize = () => {
    windowWidth.value = window.innerWidth;
    windowHeight.value = window.innerHeight;
};

const openScanMode = () => {
    scanMode.value = true;
    document.body.style.overflow = 'hidden';
};

const closeScanMode = () => {
    scanMode.value = false;
    document.body.style.overflow = '';
};

// Escape closes it, and the scroll lock is released if the component goes away
// while open — a stuck lock would leave the whole app unscrollable.
const onKeydown = (event) => {
    if (event.key === 'Escape') closeScanMode();
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    document.body.style.overflow = '';
});
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
                <BaseButton variant="outline" size="sm" @click="printCard" icon="newspaper">
                    Print
                </BaseButton>
                <BaseButton variant="primary" size="sm" href="/my/id-card/download" external icon="arrow-down-tray">
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

        <!-- The reason members open this page, so it comes before the ID card:
             the same code as the one on the card, at a size a scanner can read. -->
        <div v-if="idCard" class="mt-6 print:hidden">
            <div class="flex flex-col items-center gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:gap-6">
                <div class="shrink-0 rounded-lg border border-slate-200 bg-white p-3">
                    <QrCode :value="idCard.qr_value" :size="180" />
                </div>

                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <h2 class="text-base font-semibold text-slate-900">Attendance QR</h2>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                        Show this at the venue for attendance scanning. Open it full screen so it scans
                        cleanly in bright light.
                    </p>
                    <p class="mt-3 font-mono text-lg font-semibold tracking-tight text-brand-700">
                        {{ idCard.proctad_id }}
                    </p>
                    <p class="text-xs text-slate-400">Read this out if the scanner can't pick up the code.</p>

                    <BaseButton class="mt-4" variant="primary" size="sm" @click="openScanMode" icon="qr-code">
                        Show full screen
                    </BaseButton>
                </div>
            </div>
        </div>

        <div v-if="idCard" class="mt-8 grid gap-8 lg:grid-cols-5">
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

    <!-- Deliberately not a BaseModal: no card, no chrome, no dimmed backdrop.
         A scanner needs maximum white around a maximum-size code, and anything
         drawn over it costs contrast. Sized in viewport units so it fills a
         phone held up at a gate and stays square on a desktop. -->
    <div
        v-if="scanMode && idCard"
        class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-white p-6 print:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Attendance QR code, full screen"
        @click="closeScanMode"
    >
        <QrCode
            :value="idCard.qr_value"
            :size="Math.min(520, Math.round(Math.min(windowWidth, windowHeight) * 0.72))"
        />

        <div class="text-center">
            <p class="font-mono text-2xl font-bold tracking-tight text-slate-900">{{ idCard.proctad_id }}</p>
            <p class="mt-1 text-base font-medium text-slate-700">{{ idCard.name }}</p>
        </div>

        <p class="text-xs text-slate-400">Tap anywhere to close</p>
    </div>
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
