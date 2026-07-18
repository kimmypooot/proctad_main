<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BaseAccordion from '@/Components/BaseAccordion.vue';
import BaseButton from '@/Components/BaseButton.vue';
import AppIcon from '@/Components/AppIcon.vue';

const groups = [
    {
        heading: 'About the Program',
        items: [
            {
                question: 'What is ProCTAD?',
                answer: 'The Professionalized Corps of Test Administrators Database (ProCTAD) is a program of the Civil Service Commission Regional Office VIII that accredits, trains, and professionalizes individuals who administer civil service examinations across Eastern Visayas.',
            },
            {
                question: 'Who manages ProCTAD?',
                answer: 'ProCTAD is managed by the Civil Service Commission Regional Office VIII (CSC RO VIII), based in Palo, Leyte.',
            },
            {
                question: 'Why was ProCTAD established?',
                answer: 'ProCTAD was established to ensure that every civil service examination in the region is administered by trained, credible, and accountable professionals, protecting the integrity of the merit-based recruitment system.',
            },
        ],
    },
    {
        heading: 'Application & Membership',
        items: [
            {
                question: 'Who can apply to become a Test Administrator?',
                answer: 'Applicants with civil service eligibility, relevant experience, good moral character, and the willingness to complete the required training may apply. See the Qualifications page for the full list.',
            },
            {
                question: 'Is there a fee to register?',
                answer: 'No. Registration to the ProCTAD portal and application to the corps are free of charge.',
            },
            {
                question: 'What documents do I need to prepare?',
                answer: 'You will typically need proof of civil service eligibility, a government-issued ID, and other supporting documents. The complete checklist is shown inside the portal after registration.',
            },
            {
                question: 'How long does the evaluation process take?',
                answer: 'Applications are typically evaluated within 15 working days from the submission of complete documentary requirements.',
            },
        ],
    },
    {
        heading: 'Trainings & Deployment',
        items: [
            {
                question: 'Is training mandatory?',
                answer: 'Yes. All applicants must complete the prescribed ProCTAD orientation and training program before certification.',
            },
            {
                question: 'How are test administrators deployed?',
                answer: 'Certified administrators are engaged for scheduled examinations based on location, availability, and examination requirements. Official notices are sent through the portal and email.',
            },
            {
                question: 'Do certifications expire?',
                answer: 'Certifications are subject to periodic renewal and continuing training requirements as prescribed by CSC RO VIII.',
            },
        ],
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Frequently Asked Questions">
            <meta
                head-key="description"
                name="description"
                content="Answers to frequently asked questions about the Professionalized Corps of Test Administrators."
            >
        </Head>

        <PageHeader
            title="Frequently Asked Questions"
            description="Everything you need to know about ProCTAD, membership, and certification."
            :breadcrumbs="[{ label: 'FAQs' }]"
        />

        <!-- Anchor navigation -->
        <nav class="sticky top-16 z-20 border-b border-slate-200 bg-white/95 py-3 backdrop-blur-sm" aria-label="FAQ categories">
            <div class="mx-auto flex max-w-3xl items-center gap-6 overflow-x-auto px-4 sm:px-6 lg:px-8">
                <span class="shrink-0 text-xs font-semibold uppercase tracking-widest text-slate-400">Jump to:</span>
                <a
                    v-for="group in groups"
                    :key="group.heading"
                    :href="`#faq-${group.heading.toLowerCase().replace(/\s+/g, '-')}`"
                    class="shrink-0 rounded-full bg-slate-100 px-4 py-1.5 text-xs font-medium text-slate-700 transition-colors duration-200 hover:bg-brand-100 hover:text-brand-700"
                >
                    {{ group.heading }}
                </a>
            </div>
        </nav>

        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-3xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div
                    v-for="group in groups"
                    :key="group.heading"
                    :id="`faq-${group.heading.toLowerCase().replace(/\s+/g, '-')}`"
                    class="reveal"
                >
                    <h2 class="mb-4 text-xl font-semibold text-slate-900">{{ group.heading }}</h2>
                    <BaseAccordion :items="group.items" />
                </div>

                <div class="reveal rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
                    <h2 class="text-lg font-semibold text-slate-900">Still have questions?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Our team at CSC Regional Office VIII is ready to help.
                    </p>
                    <div class="mt-6">
                        <BaseButton href="/contact" variant="primary">
                            Contact Us
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </BaseButton>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
