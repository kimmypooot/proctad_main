<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const sections = [
    {
        heading: '1. Introduction',
        body: 'The Civil Service Commission Regional Office VIII (CSC RO VIII) is committed to protecting your personal data in compliance with Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012, its Implementing Rules and Regulations, and relevant issuances of the National Privacy Commission.',
    },
    {
        heading: '2. Information We Collect',
        body: 'When you register with the ProCTAD portal, we collect personal information including your full name, email address, mobile number, and the documentary requirements you submit in support of your application, such as proof of eligibility and government-issued identification.',
    },
    {
        heading: '3. How We Use Your Information',
        body: 'Your personal data is used solely for legitimate purposes related to the administration of the ProCTAD program: processing your application, verifying your qualifications, scheduling trainings, communicating official notices, and maintaining the roster of certified test administrators.',
    },
    {
        heading: '4. Data Sharing and Disclosure',
        body: 'We do not sell or share your personal data with third parties for commercial purposes. Your data may be shared within the Civil Service Commission and with authorized government agencies only when required for the program or mandated by law.',
    },
    {
        heading: '5. Data Security',
        body: 'We implement appropriate organizational, physical, and technical security measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction.',
    },
    {
        heading: '6. Data Retention',
        body: 'Personal data is retained only for as long as necessary to fulfill the purposes for which it was collected, or as required by applicable records retention laws and regulations.',
    },
    {
        heading: '7. Your Rights',
        body: 'Under the Data Privacy Act, you have the right to be informed, to access, to object, to erasure or blocking, to damages, to file a complaint, to rectify, and to data portability. To exercise these rights, contact our Data Protection Officer through the contact details below.',
    },
    {
        heading: '8. Contact the Data Protection Officer',
        body: 'For privacy-related inquiries or concerns, you may reach the CSC RO VIII Data Protection Officer at cscro8@csc.gov.ph or through the Civil Service Commission Regional Office VIII, Government Center, Candahug, Palo, Leyte.',
    },
    {
        heading: '9. Updates to This Policy',
        body: 'This Privacy Policy may be updated from time to time. Any changes will be posted on this page with the corresponding effectivity date.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Privacy Policy">
            <meta
                head-key="description"
                name="description"
                content="Privacy Policy of the ProCTAD portal, Civil Service Commission Regional Office VIII."
            >
        </Head>

        <PageHeader
            title="Privacy Policy"
            description="How we collect, use, and protect your personal data."
            :breadcrumbs="[{ label: 'Privacy Policy' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:grid lg:max-w-7xl lg:grid-cols-[1fr_14rem] lg:gap-12 lg:px-8">
                <div>
                    <p class="text-sm text-slate-500">Last updated: July 2026</p>

                    <div class="mt-8 space-y-10">
                        <section v-for="section in sections" :key="section.heading" :id="`section-${section.heading.split('.')[0]}`">
                            <h2 class="text-lg font-semibold text-slate-900">{{ section.heading }}</h2>
                            <p class="mt-3 leading-relaxed text-slate-600">{{ section.body }}</p>
                        </section>
                    </div>
                </div>

                <!-- Sticky table of contents (desktop) -->
                <nav class="hidden lg:block" aria-label="On this page">
                    <div class="sticky top-24 space-y-2 border-l-2 border-slate-100 pl-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">On this page</p>
                        <a
                            v-for="section in sections"
                            :key="section.heading"
                            :href="`#section-${section.heading.split('.')[0]}`"
                            class="block text-sm leading-relaxed text-slate-500 transition-colors duration-200 hover:text-brand-700"
                        >
                            {{ section.heading }}
                        </a>
                    </div>
                </nav>
            </div>
        </section>
    </PublicLayout>
</template>
