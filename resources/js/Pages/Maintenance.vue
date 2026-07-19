<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    /**
     * Whether a signed-in user is looking at this. Passed directly by
     * CheckMaintenanceMode — the middleware runs before Inertia's shared props
     * are assembled, so `auth.user` is not available on this page.
     */
    authenticated: { type: Boolean, default: false },
});

/**
 * Routes CheckMaintenanceMode deliberately leaves open. Someone landing here on
 * exam day needs to know these still work — otherwise they assume the whole
 * system is down and start phoning the office.
 */
const stillAvailable = [
    {
        icon: 'qr-code',
        label: 'QR verification',
        description: 'Checking a PROCTAD ID or certificate at a venue still works.',
    },
    {
        icon: 'envelope',
        label: 'Assignment confirmation links',
        description: 'Emailed links to accept or decline an assignment are unaffected.',
    },
    {
        icon: 'clipboard-check',
        label: 'Post-examination evaluation',
        description: 'The evaluation form is still open for respondents.',
    },
];
</script>

<template>
    <div class="relative isolate flex min-h-screen flex-col overflow-hidden">
        <Head title="Under Maintenance" />

        <!-- CSC facade background — same treatment as the other public pages. -->
        <div class="absolute inset-0 -z-10 bg-brand-900" aria-hidden="true">
            <img
                :src="'/images/cscbg_facade.jpeg'"
                alt=""
                class="h-full w-full object-cover opacity-25"
                aria-hidden="true"
            />
        </div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-900/80 via-brand-900/70 to-brand-900/85" aria-hidden="true" />

        <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6">
            <div class="w-full max-w-xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <header class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                    <img :src="'/images/csc-logo.png'" alt="Civil Service Commission" class="h-10 w-auto shrink-0" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                            Civil Service Commission — Regional Office VIII
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">PROCTAD Management System</p>
                    </div>
                </header>

                <div class="px-6 py-8 sm:px-8">
                    <div class="text-center">
                        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                            <AppIcon name="wrench" class="h-8 w-8" />
                        </span>
                        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">
                            We'll be back shortly
                        </h1>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            The PROCTAD portal is temporarily unavailable while we carry out scheduled maintenance.
                            Nothing has been lost — your records, assignments and certificates are safe.
                        </p>
                    </div>

                    <!-- The genuinely useful part: what a member can still do right now. -->
                    <div class="mt-7 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Still available</p>
                        <ul class="mt-3 space-y-3">
                            <li v-for="item in stillAvailable" :key="item.label" class="flex gap-3">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-brand-700 ring-1 ring-slate-200">
                                    <AppIcon :name="item.icon" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
                                    <p class="text-xs leading-relaxed text-slate-500">{{ item.description }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Urgent concerns</p>
                        <div class="mt-2 flex flex-col gap-1.5 text-sm sm:flex-row sm:items-center sm:gap-5">
                            <a href="mailto:ro08.esd@csc.gov.ph" class="inline-flex items-center gap-2 font-medium text-brand-700 hover:underline">
                                <AppIcon name="envelope" class="h-4 w-4 shrink-0" />
                                ro08.esd@csc.gov.ph
                            </a>
                            <a href="tel:+63531234567" class="inline-flex items-center gap-2 font-medium text-brand-700 hover:underline">
                                <AppIcon name="phone" class="h-4 w-4 shrink-0" />
                                (053) 123-4567
                            </a>
                        </div>
                    </div>

                    <!-- Signed in and still seeing this: they are a member, since
                         staff pass straight through. /login would bounce them back
                         here via the guest redirect, so offer the way out instead. -->
                    <div v-if="authenticated" class="mt-6 border-t border-slate-100 pt-5 text-center">
                        <p class="text-xs text-slate-500">
                            You're signed in, but the portal is closed to members during maintenance.
                        </p>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            type="button"
                            class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline"
                        >
                            <AppIcon name="arrow-right-on-rectangle" class="h-4 w-4" />
                            Sign out
                        </Link>
                    </div>

                    <p v-else class="mt-6 text-center text-xs text-slate-400">
                        Commission staff can
                        <Link href="/login" class="font-semibold text-brand-700 hover:underline">sign in here</Link>.
                    </p>
                </div>
            </div>
        </main>

        <footer class="pb-8 text-center text-xs text-white/70">
            &copy; {{ new Date().getFullYear() }} Civil Service Commission Regional Office VIII
        </footer>
    </div>
</template>
