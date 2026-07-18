<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SectionHeader from '@/Components/SectionHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';

const pillars = [
    {
        icon: 'shield-check',
        title: 'What is ProCTAD?',
        text: 'The Professionalized Corps of Test Administrators is an accreditation and development program that builds a reliable pool of trained examination administrators for the Civil Service Commission Regional Office VIII.',
    },
    {
        icon: 'flag',
        title: 'Our Purpose',
        text: 'To professionalize the administration of civil service examinations by setting standards for competence, conduct, and continuous development among test administrators.',
    },
    {
        icon: 'light-bulb',
        title: 'Our Mission',
        text: 'To develop and sustain a corps of competent, credible, and service-oriented test administrators who uphold the integrity of every examination.',
    },
    {
        icon: 'star',
        title: 'Our Vision',
        text: 'A trusted, world-class corps of examination professionals enabling fair and credible entry into the Philippine civil service.',
    },
];

const importance = [
    {
        icon: 'scale',
        title: 'Guarding Meritocracy',
        text: 'Civil service examinations are the gateway to government careers. Professional administration ensures every examinee is assessed fairly.',
    },
    {
        icon: 'building-library',
        title: 'Institutional Credibility',
        text: 'A professionalized corps strengthens public trust in the Civil Service Commission and its examination processes.',
    },
    {
        icon: 'users',
        title: 'Regional Capability',
        text: 'ProCTAD ensures Region VIII always has a ready, qualified pool of administrators for examinations of any scale.',
    },
];

const milestones = [
    { year: '2024', event: 'ProCTAD program conceptualized and developed by CSC RO VIII' },
    { year: '2025 Q1', event: 'Pilot implementation with initial batch of test administrators' },
    { year: '2025 Q3', event: 'Full rollout across Region VIII field offices' },
    { year: '2026', event: 'Digital transformation with the ProCTAD online portal' },
    { year: '2026+', event: 'Continued expansion and professional development programs' },
];
</script>

<template>
    <PublicLayout>
        <Head title="About ProCTAD">
            <meta
                head-key="description"
                name="description"
                content="Learn about the Professionalized Corps of Test Administrators — its purpose, mission, vision, and importance to the Civil Service Commission."
            >
        </Head>

        <PageHeader
            title="About ProCTAD"
            description="The Professionalized Corps of Test Administrators of the Civil Service Commission Regional Office VIII."
            :breadcrumbs="[{ label: 'About' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:gap-8">
                    <article
                        v-for="(pillar, idx) in pillars"
                        :key="pillar.title"
                        class="reveal group rounded-xl border border-slate-100 bg-gradient-to-br from-white to-brand-50/20 p-8 transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-md"
                        :style="{ transitionDelay: `${idx * 100}ms` }"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <AppIcon :name="pillar.icon" class="h-6 w-6" />
                        </span>
                        <h2 class="mt-5 text-xl font-semibold text-slate-900">{{ pillar.title }}</h2>
                        <p class="mt-3 leading-relaxed text-slate-600">{{ pillar.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Program timeline -->
        <section class="bg-slate-50 py-16 sm:py-24" aria-labelledby="timeline-heading">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="Program Milestones"
                    title="The ProCTAD Journey"
                    description="Key milestones in the development of the Professionalized Corps of Test Administrators."
                />

                <ol class="relative mt-12 space-y-8 border-l-2 border-brand-200 pl-8 sm:pl-10">
                    <li
                        v-for="(milestone, idx) in milestones"
                        :key="milestone.year"
                        class="reveal relative"
                        :style="{ transitionDelay: `${idx * 100}ms` }"
                    >
                        <span
                            class="absolute -left-[2.85rem] flex h-6 w-6 items-center justify-center rounded-full border-2 border-brand-300 bg-white sm:-left-[3.35rem]"
                            aria-hidden="true"
                        >
                            <span class="h-2 w-2 rounded-full bg-brand-600" />
                        </span>
                        <div class="rounded-xl border border-slate-100 bg-white p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-sm sm:p-6">
                            <p class="text-xs font-bold uppercase tracking-widest text-accent-500">
                                {{ milestone.year }}
                            </p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-700 sm:text-base">
                                {{ milestone.event }}
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="Why It Matters"
                    title="Importance in the Civil Service Commission"
                    description="ProCTAD plays a critical role in protecting the fairness and credibility of the civil service examination system."
                />

                <div class="mt-12 grid gap-6 lg:mt-16 lg:grid-cols-3 lg:gap-8">
                    <article
                        v-for="(item, idx) in importance"
                        :key="item.title"
                        class="reveal group rounded-xl border border-slate-100 bg-gradient-to-br from-white to-brand-50/20 p-8 transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-md"
                        :style="{ transitionDelay: `${idx * 100}ms` }"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <AppIcon :name="item.icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ item.text }}</p>
                    </article>
                </div>

                <div class="reveal mt-14 text-center">
                    <BaseButton href="/register" variant="primary" size="lg">
                        Join the Corps
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
