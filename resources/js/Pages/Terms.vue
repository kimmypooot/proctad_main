<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const sections = [
    {
        heading: '1. Acceptance of Terms',
        body: 'By accessing or using the ProCTAD portal, you agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, please discontinue use of the portal.',
    },
    {
        heading: '2. Purpose of the Portal',
        body: 'The ProCTAD portal is the official online platform of the Civil Service Commission Regional Office VIII for the registration, application, accreditation, and management of members of the Professionalized Corps of Test Administrators.',
    },
    {
        heading: '3. User Accounts',
        body: 'You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must provide accurate, complete, and current information during registration and keep it updated.',
    },
    {
        heading: '4. Truthfulness of Information',
        body: 'All information and documents submitted through the portal must be true and correct. Submission of falsified information or documents is a ground for disqualification and may subject the applicant to administrative and criminal liability under applicable laws.',
    },
    {
        heading: '5. Acceptable Use',
        body: 'You agree to use the portal only for its intended purposes. Any attempt to gain unauthorized access, disrupt services, or misuse the portal or the data it contains is strictly prohibited.',
    },
    {
        heading: '6. Intellectual Property',
        body: 'All content on this portal, including text, graphics, logos, and design, is the property of the Civil Service Commission or its licensors and is protected by applicable intellectual property laws.',
    },
    {
        heading: '7. Limitation of Liability',
        body: 'CSC RO VIII strives to keep the portal available and accurate but does not warrant uninterrupted or error-free operation. To the extent permitted by law, CSC RO VIII shall not be liable for damages arising from the use or inability to use the portal.',
    },
    {
        heading: '8. Suspension and Termination',
        body: 'CSC RO VIII may suspend or terminate accounts that violate these terms, submit fraudulent information, or compromise the security and integrity of the examination administration program.',
    },
    {
        heading: '9. Governing Law',
        body: 'These Terms and Conditions are governed by the laws of the Republic of the Philippines. Any disputes shall be resolved before the appropriate Philippine authorities and courts.',
    },
    {
        heading: '10. Changes to These Terms',
        body: 'CSC RO VIII reserves the right to modify these Terms and Conditions at any time. Continued use of the portal after changes are posted constitutes acceptance of the revised terms.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Terms and Conditions">
            <meta
                head-key="description"
                name="description"
                content="Terms and Conditions governing the use of the ProCTAD portal, Civil Service Commission Regional Office VIII."
            >
        </Head>

        <PageHeader
            title="Terms and Conditions"
            description="The rules governing your use of the ProCTAD portal."
            :breadcrumbs="[{ label: 'Terms and Conditions' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500">Last updated: July 2026</p>

                <div class="mt-8 space-y-10">
                    <section v-for="section in sections" :key="section.heading">
                        <h2 class="text-lg font-semibold text-slate-900">{{ section.heading }}</h2>
                        <p class="mt-3 leading-relaxed text-slate-600">{{ section.body }}</p>
                    </section>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
