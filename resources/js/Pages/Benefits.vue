<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';

const benefits = [
    {
        icon: 'check-badge',
        title: 'Professional Recognition',
        text: 'Receive official accreditation as a Certified Test Administrator, a credential recognized across the Civil Service Commission and partner agencies.',
    },
    {
        icon: 'chart-bar',
        title: 'Capacity Building',
        text: 'Attend structured trainings on examination administration, security protocols, and handling of examination materials.',
    },
    {
        icon: 'book-open',
        title: 'Continuous Learning',
        text: 'Stay current with refresher courses, updated protocols, and learning resources available exclusively to members.',
    },
    {
        icon: 'building-office',
        title: 'Government Service Opportunities',
        text: 'Participate in official CSC examination deployments, regional assessments, and special testing activities.',
    },
    {
        icon: 'sparkles',
        title: 'Professional Development',
        text: 'Build a track record of public service and grow credentials that strengthen your professional profile.',
    },
    {
        icon: 'user-group',
        title: 'Professional Network',
        text: 'Connect with a regional community of accredited administrators, examiners, and CSC officials.',
    },
    {
        icon: 'trophy',
        title: 'Service Recognition',
        text: 'Outstanding administrators are recognized in regional programs and commendations.',
    },
    {
        icon: 'clipboard-check',
        title: 'Priority Engagement',
        text: 'Certified members are prioritized for examination administration engagements in their locality.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Benefits">
            <meta
                head-key="description"
                name="description"
                content="Discover the benefits of joining the Professionalized Corps of Test Administrators — recognition, training, and government service opportunities."
            >
        </Head>

        <PageHeader
            title="Membership Benefits"
            description="What you gain as a member of the Professionalized Corps of Test Administrators."
            :breadcrumbs="[{ label: 'Benefits' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                    <article
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="reveal group rounded-xl border border-slate-200 bg-white p-8 transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-md"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-50 text-brand-600 transition-colors duration-300 group-hover:bg-brand-600 group-hover:text-white">
                            <AppIcon :name="benefit.icon" class="h-6 w-6" />
                        </span>
                        <h2 class="mt-5 text-lg font-semibold text-slate-900">{{ benefit.title }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ benefit.text }}</p>
                    </article>
                </div>

                <div class="reveal mt-14 text-center">
                    <BaseButton href="/qualifications" variant="outline" size="lg">
                        See if you qualify
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
