<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import { useToasts } from '@/Composables/useToasts';

/**
 * Chrome for the public /scan/{token} page. Deliberately not PublicLayout:
 * there is no signed-in user and no site navigation to offer — the person
 * holding the phone has one job, and everything on screen should serve it.
 *
 * The event and venue live here rather than in the page body so the context a
 * scan is being recorded against stays fixed while the body changes per scan.
 */
const props = defineProps({
    session: { type: Object, required: true },
});

const page = usePage();
const { push: pushToast } = useToasts();

watch(() => page.props.flash, (flash) => {
    if (flash?.success) pushToast('success', flash.success);
    if (flash?.error) pushToast('error', flash.error);
}, { immediate: true });

/**
 * Warn while there is still time to get a fresh link, rather than at the
 * moment scanning stops working mid-queue.
 *
 * The clock has to tick. This was `Date.now()` read inside a computed, which
 * takes no reactive dependency on time — so it was evaluated once, on first
 * render, and never again. A link opened at 7am for an examination running to
 * 5pm never turned gold: precisely the all-day case the warning is for.
 */
const now = ref(Date.now());
let clock = null;

onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 30_000)));
onBeforeUnmount(() => clearInterval(clock));

const msRemaining = computed(() => {
    const expiry = Date.parse(props.session.expires_at_iso);

    return Number.isNaN(expiry) ? null : expiry - now.value;
});

const expired = computed(() => msRemaining.value !== null && msRemaining.value <= 0);
const expiringSoon = computed(() => msRemaining.value !== null && msRemaining.value > 0 && msRemaining.value < 60 * 60 * 1000);

/**
 * Inside the last hour the absolute time stops being the useful number — "until
 * 5:00 PM" needs the operator to work out how long that is while holding a
 * queue. Both are shown, so there is still something to quote when asking the
 * office for a fresh link.
 */
const expiryLabel = computed(() => {
    if (expired.value) return 'Link expired';
    if (! expiringSoon.value) return `Until ${props.session.expires_at}`;

    const minutes = Math.max(1, Math.round(msRemaining.value / 60_000));

    return `Until ${props.session.expires_at} · ${minutes} min left`;
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <!-- pt clears the status bar / notch when the link is saved to the home
             screen and opened standalone; env(...) is 0 in an ordinary browser. -->
        <header class="bg-gradient-to-br from-brand-800 to-brand-600 text-white shadow-lg">
            <div class="mx-auto w-full max-w-3xl px-4 pb-4 pt-[calc(1rem_+_env(safe-area-inset-top))] sm:px-6 sm:pb-5 sm:pt-[calc(1.25rem_+_env(safe-area-inset-top))]">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 p-1.5 ring-1 ring-white/20">
                        <img :src="'/images/brand/proctad-logo.png'" alt="" class="h-full w-full object-contain">
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/90">
                            Attendance Scanner
                        </p>
                        <p class="truncate text-base leading-tight font-bold sm:text-lg">
                            {{ session.event ?? 'PROCTAD' }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span
                        v-if="session.venue"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-medium ring-1 ring-white/15"
                    >
                        <AppIcon name="building-office" class="h-3.5 w-3.5" />
                        {{ session.venue }}
                    </span>
                    <span
                        v-if="session.label"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-medium ring-1 ring-white/15"
                    >
                        <AppIcon name="map-pin" class="h-3.5 w-3.5" />
                        {{ session.label }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold"
                        :class="expired
                            ? 'bg-accent-600 text-white'
                            : expiringSoon ? 'bg-gold-400 text-brand-950' : 'bg-white/10 text-white ring-1 ring-white/15'"
                        :role="expiringSoon || expired ? 'status' : undefined"
                    >
                        <AppIcon :name="expired ? 'exclamation-triangle' : 'clock'" class="h-3.5 w-3.5" />
                        {{ expiryLabel }}
                    </span>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-5 sm:px-6 sm:py-6">
            <slot />
        </main>

        <!-- pb clears the home-indicator bar on gesture-nav phones. -->
        <footer class="mx-auto w-full max-w-3xl px-4 pb-[calc(1.5rem_+_env(safe-area-inset-bottom))] sm:px-6">
            <p class="flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                <AppIcon name="lock-closed" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <span>
                    Temporary scanning link issued by <strong class="font-semibold text-slate-800">{{ session.issued_by }}</strong>.
                    Keep it within your venue — anyone who opens it can record attendance for this event.
                </span>
            </p>

            <!--
                Data Privacy Act notice (RA 10173). This page displays a named
                individual's personal data to whoever is holding the link, which
                makes a purpose-and-controller statement an obligation, not a
                courtesy. The two sentences that matter stay visible; the detail
                sits behind a disclosure so it does not crowd out the operator's
                actual work.
            -->
            <div class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                <p>
                    <strong class="font-semibold text-slate-800">Privacy notice.</strong>
                    Personal data shown here is processed by the
                    <strong class="font-semibold text-slate-800">Civil Service Commission Regional Office VIII</strong>
                    for the sole purpose of verifying identity and recording examination-day attendance.
                </p>
                <details class="group mt-2">
                    <summary class="cursor-pointer font-semibold text-brand-700 marker:content-none hover:underline">
                        <span class="group-open:hidden">Read the full notice</span>
                        <span class="hidden group-open:inline">Hide the full notice</span>
                    </summary>
                    <div class="mt-2 space-y-2 border-l-2 border-slate-200 pl-3">
                        <p>
                            <strong class="font-semibold text-slate-800">What is shown:</strong>
                            the name, PROCTAD or OEP identification number, and examination assignment of the
                            person whose code is scanned. Nothing else about them is retrievable from this page.
                        </p>
                        <p>
                            <strong class="font-semibold text-slate-800">Who may use this link:</strong>
                            examination personnel at this venue only, for the duration shown above. It stops
                            working when it expires, and the office that issued it can revoke it at any time.
                        </p>
                        <p>
                            <strong class="font-semibold text-slate-800">Your rights:</strong>
                            under the Data Privacy Act of 2012 (RA 10173) you may ask what personal data is held
                            about you and request its correction. Address requests to the Civil Service Commission
                            Regional Office VIII.
                        </p>
                    </div>
                </details>
            </div>

            <p class="mt-3 text-center text-xs text-slate-500">
                Civil Service Commission Regional Office VIII — PROCTAD Management System
            </p>
        </footer>

        <!-- Bottom-anchored: the verdict and the sync bar own the top of this
             page, and a toast over either of them hides the answer. -->
        <ToastContainer position="bottom" />
    </div>
</template>
