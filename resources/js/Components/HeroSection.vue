<script setup>
import AppIcon from './AppIcon.vue';
import BaseButton from './BaseButton.vue';
import BaseBadge from './BaseBadge.vue';
</script>

<template>
    <section class="relative overflow-hidden bg-brand-800 text-white" aria-labelledby="hero-heading">
        <!-- Subtle background pattern -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true">
            <svg class="h-full w-full" viewBox="0 0 800 400" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <pattern id="hero-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                        <path d="M48 0H0v48" fill="none" stroke="currentColor" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="800" height="400" fill="url(#hero-grid)" />
            </svg>
        </div>
        <div class="pointer-events-none absolute -left-32 top-1/2 h-96 w-96 -translate-y-1/2 rounded-full bg-brand-600/50 blur-3xl" aria-hidden="true" />

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-28">
            <div class="max-w-2xl animate-fade-up">
                <BaseBadge variant="accent" class="mb-6 !bg-white/10 !text-white !ring-white/20">
                    <AppIcon name="check-badge" class="h-3.5 w-3.5 text-accent-300" />
                    Civil Service Commission Regional Office VIII
                </BaseBadge>

                <h1 id="hero-heading" class="text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-[3.4rem]">
                    Professionalized Corps of
                    <span class="text-brand-200">Test Administrators</span>
                </h1>

                <p class="mt-6 max-w-xl text-base leading-relaxed text-brand-100 sm:text-lg">
                    Building a corps of competent, credible, and professional examination
                    administrators who safeguard the integrity of civil service examinations
                    across Eastern Visayas.
                </p>

                <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                    <BaseButton href="/register" variant="accent" size="lg">
                        Register Now
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                    <BaseButton href="/about" variant="white" size="lg">
                        Learn More
                    </BaseButton>
                </div>

                <ul class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm text-brand-200">
                    <li class="flex items-center gap-2">
                        <AppIcon name="shield-check" class="h-4 w-4 text-brand-300" />
                        Official CSC Program
                    </li>
                    <li class="flex items-center gap-2">
                        <AppIcon name="check-circle" class="h-4 w-4 text-brand-300" />
                        Free Registration
                    </li>
                </ul>
            </div>

            <!-- Illustration placeholder -->
            <div class="relative hidden lg:block animate-fade-in" aria-hidden="true">
                <div class="relative mx-auto aspect-[4/3] w-full max-w-lg rounded-2xl bg-white/5 p-8 ring-1 ring-white/10">
                    <div class="flex h-full flex-col items-center justify-center gap-6 rounded-xl border border-dashed border-white/20">
                        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white/10">
                            <AppIcon name="clipboard-check" class="h-12 w-12 text-brand-100" />
                        </span>
                        <div class="text-center">
                            <p class="font-semibold text-white">Trusted Examination Administration</p>
                            <p class="mt-1 text-sm text-brand-200">Serving Region VIII with integrity</p>
                        </div>
                    </div>

                    <!-- Floating accreditation card -->
                    <div class="absolute -bottom-6 -left-6 flex items-center gap-3 rounded-xl bg-white px-5 py-4 shadow-lg">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                            <AppIcon name="check-badge" class="h-5 w-5 text-emerald-600" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Certified Administrator</p>
                            <p class="text-xs text-slate-500">Accredited by CSC RO VIII</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
