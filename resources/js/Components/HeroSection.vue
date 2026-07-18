<script setup>
import AppIcon from './AppIcon.vue';
import BaseButton from './BaseButton.vue';
</script>

<template>
    <section class="relative overflow-hidden text-white" aria-labelledby="hero-heading">
        <!-- CSC facade background image -->
        <div class="absolute inset-0 bg-brand-900" aria-hidden="true">
            <img
                :src="'/images/cscbg_facade.jpeg'"
                alt=""
                class="h-full w-full object-cover opacity-30"
                aria-hidden="true"
            />
        </div>
        <!-- Dark overlay for readability -->
        <div class="absolute inset-0 bg-gradient-to-b from-brand-900/75 via-brand-900/65 to-brand-900/80" aria-hidden="true" />

        <!-- Philippine map watermark (Region VIII silhouette) -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.04] mix-blend-overlay" aria-hidden="true">
            <svg class="h-full w-full" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" fill="currentColor">
                <path d="M400 50C250 50 120 150 100 280c-5 35 0 70 15 100l30 60c10 20 25 35 45 45l60 30c20 10 40 15 65 15h170c25 0 50-5 70-15l60-30c20-10 35-25 45-45l30-60c15-30 20-65 15-100C680 150 550 50 400 50z" opacity="0.6"/>
                <circle cx="400" cy="250" r="60" opacity="0.4"/>
                <circle cx="400" cy="250" r="30" opacity="0.3"/>
                <path d="M380 220h40l-20 35 20 35h-40l-20-35 20-35z" opacity="0.5"/>
            </svg>
        </div>
        <!-- Grid pattern overlay -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.05]" aria-hidden="true">
            <svg class="h-full w-full" viewBox="0 0 800 400" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <pattern id="hero-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                        <path d="M48 0H0v48" fill="none" stroke="currentColor" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="800" height="400" fill="url(#hero-grid)" />
            </svg>
        </div>
        <!-- Glow orbs -->
        <div class="pointer-events-none absolute -left-32 top-1/2 h-96 w-96 -translate-y-1/2 rounded-full bg-brand-600/30 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -right-32 bottom-0 h-64 w-64 rounded-full bg-accent-500/10 blur-3xl" aria-hidden="true" />

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-28">
            <div class="max-w-2xl animate-fade-up">
                <div class="mb-6 flex items-center gap-2 sm:gap-3">
                    <img :src="'/images/csc-logo.png'" alt="CSC Logo" class="h-16 w-auto drop-shadow-md sm:h-20" />
                    <img :src="'/images/bagong_pilipinas.png'" alt="Bagong Pilipinas" class="h-16 w-auto drop-shadow-md sm:h-20" />
                    <img :src="'/images/lingkod_bayani.png'" alt="Lingkod Bayani" class="h-16 w-auto drop-shadow-md sm:h-20" />
                </div>

                <h1 id="hero-heading" class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-[3.4rem]">
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

            </div>

            <!-- Illustration card -->
            <div class="relative hidden lg:block animate-fade-in" aria-hidden="true">
                <div class="relative mx-auto aspect-[4/3] w-full max-w-lg rounded-2xl bg-gradient-to-br from-white/10 to-white/5 p-8 ring-1 ring-white/10 shadow-2xl">
                    <div class="flex h-full flex-col items-center justify-center gap-6 rounded-xl border border-dashed border-white/20 backdrop-blur-sm">
                        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white/10">
                            <AppIcon name="clipboard-check" class="h-12 w-12 text-brand-100" />
                        </span>
                        <div class="text-center">
                            <p class="font-semibold text-white">Trusted Examination Administration</p>
                            <p class="mt-1 text-sm text-brand-200">Serving Region VIII with integrity</p>
                        </div>
                    </div>

                    <!-- Floating accreditation card -->
                    <div class="absolute -bottom-6 -left-6 flex items-center gap-3 rounded-xl bg-white px-5 py-4 shadow-lg animate-scale-in">
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
