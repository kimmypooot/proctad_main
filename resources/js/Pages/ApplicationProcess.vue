<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';

const steps = [
    {
        icon: 'user-plus',
        title: 'Register Account',
        text: 'Create your ProCTAD account with your basic personal information and a secure password.',
    },
    {
        icon: 'envelope-open',
        title: 'Verify Email',
        text: 'Check your inbox and click the verification link to activate your account.',
    },
    {
        icon: 'identification',
        title: 'Complete Profile',
        text: 'Provide your complete personal, educational, and professional information.',
    },
    {
        icon: 'cloud-arrow-up',
        title: 'Upload Documentary Requirements',
        text: 'Submit clear digital copies of your eligibility, government ID, and other required documents.',
    },
    {
        icon: 'clipboard-check',
        title: 'Evaluation',
        text: 'CSC Regional Office VIII evaluates your application and verifies your submitted documents.',
    },
    {
        icon: 'check-circle',
        title: 'Approval',
        text: 'Once approved, you will receive an official notice with your training schedule.',
    },
    {
        icon: 'check-badge',
        title: 'Become a Certified Test Administrator',
        text: 'Complete the required training and receive your certification as a member of the corps.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Application Process">
            <meta
                head-key="description"
                name="description"
                content="Step-by-step guide to becoming a Certified Test Administrator with CSC Regional Office VIII."
            >
        </Head>

        <PageHeader
            title="Application Process"
            description="Seven clear steps from registration to certification."
            :breadcrumbs="[{ label: 'Application Process' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <ol class="space-y-0">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="reveal relative flex gap-6 pb-12 last:pb-0"
                    >
                        <!-- Connector line -->
                        <span
                            v-if="index < steps.length - 1"
                            class="absolute left-7 top-14 h-[calc(100%-3.5rem)] w-px bg-brand-100"
                            aria-hidden="true"
                        />

                        <span class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-brand-100 bg-white text-brand-600">
                            <AppIcon :name="step.icon" class="h-6 w-6" />
                        </span>

                        <div class="pt-1">
                            <p class="text-xs font-semibold uppercase tracking-widest text-accent-500">
                                Step {{ index + 1 }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ step.title }}</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ step.text }}</p>
                        </div>
                    </li>
                </ol>

                <div class="reveal mt-14 rounded-2xl bg-brand-700 px-6 py-10 text-center text-white sm:px-12">
                    <h2 class="text-2xl font-bold tracking-tight">Ready to begin?</h2>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-brand-100">
                        Start with Step 1 today — registration is free and takes less than five minutes.
                    </p>
                    <div class="mt-6">
                        <BaseButton href="/register" variant="accent" size="lg">
                            Register Now
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </BaseButton>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
