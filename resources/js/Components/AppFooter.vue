<script setup>
import { Link } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';
import AppIcon from './AppIcon.vue';

const year = new Date().getFullYear();

const quickLinks = [
    { label: 'About ProCTAD', href: '/about' },
    { label: 'Benefits', href: '/benefits' },
    { label: 'Qualifications', href: '/qualifications' },
    { label: 'Application Process', href: '/application-process' },
    { label: 'FAQs', href: '/faqs' },
];

const legalLinks = [
    { label: 'Privacy Policy', href: '/privacy-policy' },
    { label: 'Terms and Conditions', href: '/terms-and-conditions' },
    { label: 'Contact Us', href: '/contact' },
];

const socials = [
    { label: 'Facebook', icon: 'facebook', href: 'https://www.facebook.com/cscro8' },
    { label: 'X (Twitter)', icon: 'twitter-x', href: '#' },
    { label: 'YouTube', icon: 'youtube', href: 'https://www.youtube.com/@cscro8' },
];
</script>

<template>
    <footer class="bg-brand-900 text-brand-100">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                <!-- Brand -->
                <div class="space-y-4">
                    <AppLogo variant="light" />
                    <p class="text-sm leading-relaxed text-brand-200">
                        The Professionalized Corps of Test Administrators — upholding integrity,
                        professionalism, and excellence in civil service examinations across Region VIII.
                    </p>
                    <ul class="flex gap-3" aria-label="Social media">
                        <li v-for="social in socials" :key="social.label">
                            <a
                                :href="social.href"
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-brand-100 transition-colors duration-200 hover:bg-white/20 hover:text-white"
                                :aria-label="social.label"
                            >
                                <AppIcon :name="social.icon" class="h-4 w-4" />
                            </a>
                        </li>
                    </ul>

                    <div class="flex items-center gap-3 pt-1">
                        <img :src="'/images/govph_seal.png'" alt="GOV PH Seal" class="h-23 w-auto opacity-80" />
                        <img :src="'/images/CORSeal_CSC2.png'" alt="NPC DPO Registration" class="h-25 w-auto opacity-80" />
                        <img :src="'/images/transparency-seal.png'" alt="Transparency Seal" class="h-22 w-auto opacity-80" />
                    </div>
                </div>

                <!-- Quick links -->
                <nav aria-label="Quick links">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Quick Links</h2>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in quickLinks" :key="link.href">
                            <Link :href="link.href" class="text-sm text-brand-200 transition-colors duration-200 hover:text-white">
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <!-- Legal -->
                <nav aria-label="Legal">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Information</h2>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in legalLinks" :key="link.href">
                            <Link :href="link.href" class="text-sm text-brand-200 transition-colors duration-200 hover:text-white">
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <!-- Contact -->
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Contact</h2>
                    <ul class="mt-4 space-y-3 text-sm text-brand-200">
                        <li class="flex items-start gap-3">
                            <AppIcon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                            <span>CSC Regional Office VIII, Government Center, Candahug, Palo, Leyte</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <AppIcon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                            <a href="tel:+63531234567" class="transition-colors hover:text-white">(053) 123-4567</a>
                        </li>
                        <li class="flex items-start gap-3">
                            <AppIcon name="envelope" class="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                            <a href="mailto:cscro8@csc.gov.ph" class="transition-colors hover:text-white">cscro8@csc.gov.ph</a>
                        </li>
                        <li class="flex items-start gap-3">
                            <AppIcon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                            <span>Monday – Friday, 8:00 AM – 5:00 PM</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 sm:flex-row">
                <p class="text-xs text-brand-300">
                    &copy; {{ year }} Civil Service Commission Regional Office VIII. All rights reserved.
                </p>
                <div class="flex flex-col items-center gap-1 sm:items-end">
                    <p class="font-serif text-xs font-semibold uppercase tracking-widest text-gold-400">
                        Republic of the Philippines
                    </p>
                    <p class="text-xs text-brand-300">
                        All content is publicly accessible in accordance with the Ease of Doing Business Act.
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>
