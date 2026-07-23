<script setup>
import { computed, watch } from 'vue';
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
 */
const expiringSoon = computed(() => {
    const expiry = Date.parse(props.session.expires_at_iso);

    return !Number.isNaN(expiry) && expiry - Date.now() < 60 * 60 * 1000;
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
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-white/60">
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
                        :class="expiringSoon ? 'bg-gold-400 text-brand-950' : 'bg-white/10 text-white ring-1 ring-white/15'"
                    >
                        <AppIcon name="clock" class="h-3.5 w-3.5" />
                        Until {{ session.expires_at }}
                    </span>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-5 sm:px-6 sm:py-6">
            <slot />
        </main>

        <!-- pb clears the home-indicator bar on gesture-nav phones. -->
        <footer class="mx-auto w-full max-w-3xl px-4 pb-[calc(1.5rem_+_env(safe-area-inset-bottom))] sm:px-6">
            <p class="flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-500">
                <AppIcon name="lock-closed" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                <span>
                    Temporary scanning link issued by <strong class="font-semibold text-slate-700">{{ session.issued_by }}</strong>.
                    Keep it within your venue — anyone who opens it can record attendance for this event.
                </span>
            </p>
            <p class="mt-3 text-center text-[0.7rem] text-slate-400">
                Civil Service Commission Regional Office VIII — PROCTAD Management System
            </p>
        </footer>

        <!-- Bottom-anchored: the verdict and the sync bar own the top of this
             page, and a toast over either of them hides the answer. -->
        <ToastContainer position="bottom" />
    </div>
</template>
