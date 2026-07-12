<script setup>
import { Head } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white">
        <Head title="Under Maintenance" />

        <header class="p-6">
            <AppLogo />
        </header>

        <main class="flex flex-1 items-center justify-center px-4 py-16 sm:px-6">
            <div class="max-w-md text-center animate-fade-up">
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <AppIcon name="wrench" class="h-10 w-10" />
                </span>
                <h1 class="mt-8 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Scheduled Maintenance
                </h1>
                <p class="mt-4 leading-relaxed text-slate-600">
                    The ProCTAD portal is temporarily unavailable while we perform scheduled
                    maintenance. We'll be back shortly — thank you for your patience.
                </p>
                <p class="mt-8 text-sm text-slate-500">
                    For urgent concerns, email
                    <a href="mailto:cscro8@csc.gov.ph" class="font-medium text-brand-700 hover:underline">cscro8@csc.gov.ph</a>
                </p>
            </div>
        </main>

        <footer class="p-6 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} Civil Service Commission Regional Office VIII
        </footer>
    </div>
</template>
