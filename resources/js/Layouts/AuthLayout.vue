<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseAlert from '@/Components/BaseAlert.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});

const page = usePage();
const flashVisible = ref(true);
const flash = computed(() => page.props.flash ?? {});

watch(() => page.props.flash, () => (flashVisible.value = true));

const highlights = [
    {
        icon: 'shield-check',
        title: 'Integrity First',
        text: 'Uphold the credibility of civil service examinations across Region VIII.',
    },
    {
        icon: 'academic-cap',
        title: 'Professional Growth',
        text: 'Access trainings, certifications, and continuous capability building.',
    },
    {
        icon: 'users',
        title: 'A Trusted Corps',
        text: 'Join a professionalized community of accredited test administrators.',
    },
];
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <!-- Branding panel -->
        <aside class="relative hidden w-1/2 flex-col overflow-hidden bg-brand-800 px-12 pb-12 pt-10 text-white lg:flex" aria-hidden="false">
            <!-- CSC facade background -->
            <div class="absolute inset-0" aria-hidden="true">
                <img
                    :src="'/images/cscbg_facade.jpeg'"
                    alt=""
                    class="h-full w-full scale-110 object-cover opacity-20"
                    aria-hidden="true"
                />
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-brand-900/80 via-brand-900/70 to-brand-900/85" aria-hidden="true" />

            <!-- Subtle geometric background -->
            <div class="pointer-events-none absolute inset-0 opacity-10" aria-hidden="true">
                <svg class="h-full w-full" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice">
                    <defs>
                        <pattern id="auth-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M40 0H0v40" fill="none" stroke="currentColor" stroke-width="1" />
                        </pattern>
                    </defs>
                    <rect width="400" height="400" fill="url(#auth-grid)" />
                </svg>
            </div>
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-600/40 blur-3xl" aria-hidden="true" />

            <Link href="/" class="relative z-10 inline-flex w-fit rounded-md" aria-label="ProCTAD — Home">
                <AppLogo variant="light" />
            </Link>

            <div class="relative z-10 flex flex-1 flex-col justify-center">
                <div class="max-w-md space-y-10">
                    <div>
                        <h2 class="text-3xl font-bold leading-tight tracking-tight">
                            Professionalized Corps of Test Administrators
                        </h2>
                        <p class="mt-4 leading-relaxed text-brand-100">
                            The official portal of the Civil Service Commission Regional Office VIII
                            for accrediting and developing professional examination administrators.
                        </p>
                    </div>

                    <ul class="space-y-6">
                        <li v-for="item in highlights" :key="item.title" class="flex gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/10">
                                <AppIcon :name="item.icon" class="h-5 w-5 text-brand-100" />
                            </span>
                            <div>
                                <p class="font-semibold">{{ item.title }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-brand-200">{{ item.text }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <p class="relative z-10 text-xs text-brand-300">
                &copy; {{ new Date().getFullYear() }} Civil Service Commission Regional Office VIII
            </p>
        </aside>

        <!-- Form panel -->
        <main class="flex w-full flex-col lg:w-1/2">
            <div class="flex items-center justify-between p-6 lg:justify-end">
                <Link href="/" class="rounded-md lg:hidden" aria-label="ProCTAD — Home">
                    <AppLogo />
                </Link>
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition-colors hover:text-brand-700"
                >
                    <AppIcon name="chevron-left" class="h-4 w-4" />
                    Back to website
                </Link>
            </div>

            <div class="flex flex-1 items-center justify-center px-4 pb-12 sm:px-6">
                <div class="w-full max-w-md animate-fade-up">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ title }}
                    </h1>
                    <p v-if="subtitle" class="mt-2 text-sm leading-relaxed text-slate-500">
                        {{ subtitle }}
                    </p>

                    <BaseAlert
                        v-if="flashVisible && flash.error"
                        class="mt-5"
                        variant="error"
                        dismissible
                        @dismiss="flashVisible = false"
                    >
                        {{ flash.error }}
                    </BaseAlert>

                    <BaseAlert
                        v-if="flashVisible && (flash.success || flash.status)"
                        class="mt-5"
                        variant="success"
                        dismissible
                        @dismiss="flashVisible = false"
                    >
                        {{ flash.success ?? flash.status }}
                    </BaseAlert>

                    <div class="mt-8">
                        <slot />
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
