<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import SelectInput from '@/Components/SelectInput.vue';

const details = [
    {
        icon: 'building-office',
        label: 'Office',
        lines: ['Civil Service Commission Regional Office VIII'],
    },
    {
        icon: 'map-pin',
        label: 'Address',
        lines: ['Government Center, Candahug, Palo, Leyte 6501'],
    },
    {
        icon: 'phone',
        label: 'Telephone',
        lines: ['(053) 123-4567'],
        href: 'tel:+63531234567',
    },
    {
        icon: 'envelope',
        label: 'Email',
        lines: ['cscro8@csc.gov.ph'],
        href: 'mailto:cscro8@csc.gov.ph',
    },
    {
        icon: 'clock',
        label: 'Office Hours',
        lines: ['Monday – Friday', '8:00 AM – 5:00 PM (no noon break)'],
    },
];

const subjects = ['General Inquiry', 'Application Concern', 'Training Schedule', 'Technical Support', 'Others'];

// Placeholder form — no backend endpoint yet; shows a success state client-side.
const form = ref({ name: '', email: '', subject: '', message: '' });
const sending = ref(false);
const sent = ref(false);

const submit = () => {
    sending.value = true;
    setTimeout(() => {
        sending.value = false;
        sent.value = true;
        form.value = { name: '', email: '', subject: '', message: '' };
    }, 800);
};
</script>

<template>
    <PublicLayout>
        <Head title="Contact Us">
            <meta
                head-key="description"
                name="description"
                content="Get in touch with the Civil Service Commission Regional Office VIII regarding the ProCTAD program."
            >
        </Head>

        <PageHeader
            title="Contact Us"
            description="We're here to help with your questions about ProCTAD."
            :breadcrumbs="[{ label: 'Contact' }]"
        />

        <section class="py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:gap-16 lg:px-8">
                <!-- Details -->
                <div class="reveal space-y-8 lg:col-span-2">
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Get in touch</h2>

                    <ul class="space-y-6">
                        <li v-for="detail in details" :key="detail.label" class="flex gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                <AppIcon :name="detail.icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ detail.label }}</p>
                                <template v-if="detail.href">
                                    <a :href="detail.href" class="mt-0.5 block text-sm text-slate-600 transition-colors hover:text-brand-700">
                                        {{ detail.lines[0] }}
                                    </a>
                                </template>
                                <template v-else>
                                    <p v-for="line in detail.lines" :key="line" class="mt-0.5 text-sm text-slate-600">
                                        {{ line }}
                                    </p>
                                </template>
                            </div>
                        </li>
                    </ul>

                    <!-- Google Map embed -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1234!2d124.987!3d11.155!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3309e3b3b3b3b3b3%3A0x3b3b3b3b3b3b3b3b!2sCSC%20Regional%20Office%20VIII!5e0!3m2!1sen!2sph!4v1"
                            width="100%"
                            height="280"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="CSC Regional Office VIII location on Google Maps"
                            class="w-full"
                        />
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Government Center, Candahug, Palo, Leyte
                    </p>
                </div>

                <!-- Form -->
                <div class="reveal lg:col-span-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-10">
                        <h2 class="text-xl font-semibold text-slate-900">Send us a message</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Fill out the form below and we'll get back to you within office hours.
                        </p>

                        <BaseAlert
                            v-if="sent"
                            variant="success"
                            title="Message sent"
                            dismissible
                            class="mt-6"
                            @dismiss="sent = false"
                        >
                            Thank you for reaching out. Our team will respond as soon as possible.
                        </BaseAlert>

                        <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <TextInput v-model="form.name" label="Full Name" required autocomplete="name" placeholder="Juan Dela Cruz" />
                                <TextInput v-model="form.email" label="Email Address" type="email" required autocomplete="email" placeholder="you@example.com" />
                            </div>
                            <SelectInput v-model="form.subject" label="Subject" :options="subjects" required />
                            <TextArea v-model="form.message" label="Message" :rows="5" required placeholder="How can we help you?" />

                            <BaseButton type="submit" variant="primary" size="lg" :loading="sending" :disabled="sending">
                                Send Message
                                <AppIcon name="paper-airplane" class="h-4 w-4" />
                            </BaseButton>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
