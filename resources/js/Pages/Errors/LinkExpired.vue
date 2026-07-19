<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';
</script>

<template>
    <PublicLayout>
        <Head title="Link Expired" />

        <section class="relative isolate flex min-h-[60vh] flex-col justify-center overflow-hidden px-4 py-16">
            <!-- CSC facade background — matches Assignments/Confirm. -->
            <div class="absolute inset-0 -z-10 bg-brand-900" aria-hidden="true">
                <img
                    :src="'/images/cscbg_facade.jpeg'"
                    alt=""
                    class="h-full w-full object-cover opacity-25"
                    aria-hidden="true"
                />
            </div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-900/80 via-brand-900/70 to-brand-900/85" aria-hidden="true" />

            <div class="mx-auto w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-xl">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                    <AppIcon name="exclamation-triangle" class="h-6 w-6 text-amber-600" />
                </div>

                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">This link has expired</h1>

                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    Confirmation links stay valid for a limited time and can only be opened from the original email.
                    This one is no longer active, or it was changed somewhere along the way.
                </p>

                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    Please contact your Testing Center and ask them to send a new confirmation request. Your assignment
                    itself has not been cancelled.
                </p>

                <div class="mt-8">
                    <BaseButton href="/" variant="primary" size="lg" block>Return to Homepage</BaseButton>
                </div>

                <p class="mt-8 text-xs leading-relaxed text-slate-400">
                    Civil Service Commission Regional Office VIII — PROCTAD Management System.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
