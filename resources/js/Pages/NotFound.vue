<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
</script>

<template>
    <PublicLayout>
        <Head title="Page Not Found" />

        <section class="flex min-h-[60vh] items-center justify-center px-4 py-24 sm:px-6">
            <div class="max-w-md text-center animate-fade-up">
                <p class="text-7xl font-bold tracking-tight text-brand-600 sm:text-8xl">404</p>
                <h1 class="mt-6 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Page not found
                </h1>
                <p class="mt-4 leading-relaxed text-slate-600">
                    Sorry, we couldn't find the page you're looking for. It may have been
                    moved, renamed, or no longer exists.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                    <BaseButton href="/" variant="primary">
                        <AppIcon name="home" class="h-4 w-4" />
                        Back to Home
                    </BaseButton>
                    <BaseButton href="/contact" variant="outline">Contact Support</BaseButton>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
