<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import HeroSection from '@/Components/HeroSection.vue';
import SectionHeader from '@/Components/SectionHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAccordion from '@/Components/BaseAccordion.vue';
import StatCounter from '@/Components/StatCounter.vue';

const aboutCards = [
    {
        icon: 'shield-check',
        title: 'Integrity',
        text: 'Safeguarding the credibility and security of every civil service examination.',
    },
    {
        icon: 'academic-cap',
        title: 'Professionalism',
        text: 'A trained, accredited, and continuously developed corps of administrators.',
    },
    {
        icon: 'users',
        title: 'Public Service',
        text: 'Serving examinees and agencies across Eastern Visayas with excellence.',
    },
];

const benefits = [
    {
        icon: 'check-badge',
        title: 'Professional Recognition',
        text: 'Earn official accreditation as a Certified Test Administrator of the Civil Service Commission.',
    },
    {
        icon: 'chart-bar',
        title: 'Capacity Building',
        text: 'Participate in structured trainings that strengthen your examination administration skills.',
    },
    {
        icon: 'book-open',
        title: 'Continuous Learning',
        text: 'Access learning resources, refresher courses, and updated examination protocols.',
    },
    {
        icon: 'building-office',
        title: 'Government Service Opportunities',
        text: 'Be part of official CSC examination deployments and regional assessment activities.',
    },
    {
        icon: 'sparkles',
        title: 'Professional Development',
        text: 'Grow your career with credentials recognized across the civil service community.',
    },
    {
        icon: 'user-group',
        title: 'Professional Network',
        text: 'Join a community of accredited administrators across Region VIII.',
    },
];

const steps = [
    { icon: 'user-plus', title: 'Register Account', text: 'Create your ProCTAD account online.' },
    { icon: 'envelope-open', title: 'Verify Email', text: 'Confirm your email address to activate your account.' },
    { icon: 'identification', title: 'Complete Profile', text: 'Fill in your personal and professional information.' },
    { icon: 'cloud-arrow-up', title: 'Upload Requirements', text: 'Submit your documentary requirements.' },
    { icon: 'clipboard-check', title: 'Evaluation', text: 'CSC RO VIII reviews your application.' },
    { icon: 'check-circle', title: 'Approval', text: 'Receive your official approval notice.' },
    { icon: 'check-badge', title: 'Get Certified', text: 'Become a Certified Test Administrator.' },
];

const stats = [
    { value: 1250, suffix: '+', label: 'Registered Members' },
    { value: 890, suffix: '+', label: 'Certified Test Administrators' },
    { value: 120, suffix: '+', label: 'Trainings Conducted' },
    { value: 45, suffix: '+', label: 'Partner Agencies' },
    { value: 300, suffix: '+', label: 'Completed Assessments' },
];

const faqs = [
    {
        question: 'What is ProCTAD?',
        answer: 'The Professionalized Corps of Test Administrators Database (ProCTAD) is a program of the Civil Service Commission Regional Office VIII that accredits, trains, and professionalizes individuals who administer civil service examinations in the region.',
    },
    {
        question: 'Who can apply to become a Test Administrator?',
        answer: 'Applicants with civil service eligibility, relevant experience, and good moral character may apply. Complete qualifications are listed on the Qualifications page.',
    },
    {
        question: 'Is there a fee to register?',
        answer: 'No. Registration to the ProCTAD portal is free of charge.',
    },
    {
        question: 'How long does the evaluation process take?',
        answer: 'Applications are typically evaluated within 15 working days from the submission of complete documentary requirements.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Home">
            <meta
                head-key="description"
                name="description"
                content="Official portal of the Professionalized Corps of Test Administrators Database (ProCTAD), Civil Service Commission Regional Office VIII."
            >
        </Head>

        <HeroSection />

        <!-- About preview -->
        <section class="py-16 sm:py-24" aria-labelledby="about-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="About ProCTAD"
                    title="A Professional Corps You Can Trust"
                    description="ProCTAD is the Civil Service Commission Regional Office VIII's program for building a professionalized, accredited, and continuously trained corps of examination administrators."
                />

                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:mt-16 lg:grid-cols-3 lg:gap-8">
                    <article
                        v-for="card in aboutCards"
                        :key="card.title"
                        class="reveal rounded-xl border border-slate-200 bg-white p-8 transition-shadow duration-300 hover:shadow-md"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <AppIcon :name="card.icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ card.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ card.text }}</p>
                    </article>
                </div>

                <div class="reveal mt-12 text-center">
                    <BaseButton href="/about" variant="outline">
                        Learn more about ProCTAD
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="bg-slate-50 py-16 sm:py-24" aria-labelledby="benefits-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="Membership Benefits"
                    title="Why Join the Corps"
                    description="Becoming a Certified Test Administrator opens doors to recognition, development, and meaningful public service."
                />

                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:mt-16 lg:grid-cols-3 lg:gap-8">
                    <article
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="reveal group rounded-xl border border-slate-200 bg-white p-8 transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-md"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-50 text-brand-600 transition-colors duration-300 group-hover:bg-brand-600 group-hover:text-white">
                            <AppIcon :name="benefit.icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ benefit.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ benefit.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Application process stepper -->
        <section class="py-16 sm:py-24" aria-labelledby="process-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="Application Process"
                    title="Your Path to Certification"
                    description="Seven clear steps from registration to becoming a Certified Test Administrator."
                />

                <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:mt-16 lg:grid-cols-4 xl:grid-cols-7">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="reveal relative flex flex-col items-center text-center"
                    >
                        <!-- Connector line (desktop) -->
                        <span
                            v-if="index < steps.length - 1"
                            class="absolute left-[calc(50%+2rem)] top-7 hidden h-px w-[calc(100%-4rem)] bg-slate-200 xl:block"
                            aria-hidden="true"
                        />
                        <span class="relative flex h-14 w-14 items-center justify-center rounded-full border-2 border-brand-100 bg-white text-brand-600">
                            <AppIcon :name="step.icon" class="h-6 w-6" />
                            <span class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                                {{ index + 1 }}
                            </span>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ step.title }}</h3>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-500">{{ step.text }}</p>
                    </li>
                </ol>

                <div class="reveal mt-12 text-center">
                    <BaseButton href="/application-process" variant="outline">
                        View detailed process
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>

        <!-- Post-Examination Evaluation CTA (for already-serving Test Administrators) -->
        <section class="bg-slate-50 py-16 sm:py-24" aria-labelledby="evaluation-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal flex flex-col items-center gap-8 rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center sm:px-16 lg:flex-row lg:gap-12 lg:text-left">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <AppIcon name="clipboard-check" class="h-8 w-8" />
                    </span>
                    <div class="flex-1">
                        <h2 id="evaluation-heading" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Already Served as a Test Administrator?
                        </h2>
                        <p class="mt-3 max-w-2xl text-base leading-relaxed text-slate-600">
                            Submit your Post-Examination Evaluation to help us assess venue readiness, staff performance,
                            and the overall examinee experience for future examinations.
                        </p>
                    </div>
                    <BaseButton href="/evaluation" variant="primary" size="lg" class="shrink-0">
                        Submit Evaluation
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>

        <!-- Statistics -->
        <section class="bg-brand-800 py-16 sm:py-20" aria-label="Program statistics">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 lg:grid-cols-5">
                    <StatCounter
                        v-for="stat in stats"
                        :key="stat.label"
                        :value="stat.value"
                        :suffix="stat.suffix"
                        :label="stat.label"
                    />
                </div>
            </div>
        </section>

        <!-- FAQ preview -->
        <section class="py-16 sm:py-24" aria-labelledby="faq-heading">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <SectionHeader
                    eyebrow="FAQs"
                    title="Frequently Asked Questions"
                    description="Quick answers to the most common questions about ProCTAD."
                />

                <div class="reveal mt-12">
                    <BaseAccordion :items="faqs" />
                </div>

                <div class="reveal mt-8 text-center">
                    <BaseButton href="/faqs" variant="ghost">
                        View all FAQs
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="bg-slate-50 py-16 sm:py-24" aria-labelledby="cta-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal overflow-hidden rounded-2xl bg-brand-700 px-6 py-14 text-center text-white sm:px-16 sm:py-16">
                    <h2 id="cta-heading" class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                        Ready to serve with integrity?
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-brand-100">
                        Join the Professionalized Corps of Test Administrators and be part of
                        credible civil service examinations in Region VIII.
                    </p>
                    <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                        <BaseButton href="/register" variant="accent" size="lg">
                            Register Now
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </BaseButton>
                        <BaseButton href="/contact" variant="white" size="lg">
                            Contact Us
                        </BaseButton>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
