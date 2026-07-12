<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAlert from '@/Components/BaseAlert.vue';

const qualifications = [
    {
        icon: 'check-badge',
        title: 'Civil Service Eligibility',
        text: 'Must possess appropriate civil service eligibility (professional or sub-professional) or an equivalent qualification recognized by the Commission.',
    },
    {
        icon: 'briefcase',
        title: 'Relevant Experience',
        text: 'Prior experience in examination administration, proctoring, teaching, or related government service is an advantage.',
    },
    {
        icon: 'academic-cap',
        title: 'Required Training',
        text: 'Must complete the prescribed ProCTAD orientation and training program conducted by CSC Regional Office VIII.',
    },
    {
        icon: 'shield-check',
        title: 'Good Moral Character',
        text: 'Must have no pending administrative or criminal case and must demonstrate integrity befitting a custodian of examination security.',
    },
    {
        icon: 'star',
        title: 'Professional Competence',
        text: 'Must demonstrate the knowledge, skills, and attention to detail required to administer examinations accurately and securely.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Qualifications">
            <meta
                head-key="description"
                name="description"
                content="Qualifications required to join the Professionalized Corps of Test Administrators of CSC Regional Office VIII."
            >
        </Head>

        <PageHeader
            title="Qualifications"
            description="What it takes to become a Certified Test Administrator."
            :breadcrumbs="[{ label: 'Qualifications' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <ol class="relative space-y-10 border-l-2 border-brand-100 pl-8 sm:pl-10">
                    <li
                        v-for="(item, index) in qualifications"
                        :key="item.title"
                        class="reveal relative"
                    >
                        <span
                            class="absolute -left-[3.25rem] flex h-9 w-9 items-center justify-center rounded-full border-2 border-brand-100 bg-white text-sm font-semibold text-brand-600 sm:-left-[3.75rem]"
                            aria-hidden="true"
                        >
                            {{ index + 1 }}
                        </span>
                        <div class="rounded-xl border border-slate-200 bg-white p-6 transition-shadow duration-300 hover:shadow-md sm:p-8">
                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                    <AppIcon :name="item.icon" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">{{ item.title }}</h2>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ item.text }}</p>
                                </div>
                            </div>
                        </div>
                    </li>
                </ol>

                <div class="reveal mt-12">
                    <BaseAlert variant="info" title="Documentary requirements">
                        The complete list of documentary requirements is provided during the application
                        process after you create an account.
                    </BaseAlert>
                </div>

                <div class="reveal mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                    <BaseButton href="/register" variant="primary" size="lg">
                        Start your application
                        <AppIcon name="arrow-right" class="h-4 w-4" />
                    </BaseButton>
                    <BaseButton href="/application-process" variant="outline" size="lg">
                        View application process
                    </BaseButton>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
