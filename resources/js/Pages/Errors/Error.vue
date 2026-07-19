<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    status: { type: Number, required: true },
});

/**
 * Wording is aimed at a test administrator, not a developer: say what happened
 * and what to do next. No status codes in the body copy, no stack traces, and
 * never "an error occurred" on its own — that tells nobody anything.
 */
const content = computed(() => ({
    404: {
        icon: 'magnifying-glass',
        title: 'Page not found',
        body: "The page you're looking for doesn't exist, or it may have been moved.",
        hint: 'Check the address, or head back to your dashboard.',
    },
    403: {
        icon: 'shield-check',
        title: "You don't have access to this page",
        body: 'Your account doesn\'t have permission to view this. If you think it should, your Testing Center can check your role.',
        hint: null,
    },
    500: {
        icon: 'exclamation-triangle',
        title: 'Something went wrong on our end',
        body: 'This is a problem with the system, not with anything you did. Nothing you submitted has been lost.',
        hint: 'Please try again in a few minutes. If it keeps happening, contact your Testing Center.',
    },
}[props.status] ?? {
    icon: 'exclamation-triangle',
    title: 'Something went wrong',
    body: "We couldn't complete that request.",
    hint: 'Please try again, or contact your Testing Center if it keeps happening.',
}));
</script>

<template>
    <PublicLayout>
        <Head :title="content.title" />

        <section class="relative isolate flex min-h-[60vh] flex-col justify-center overflow-hidden px-4 py-16">
            <!-- CSC facade background — matches LinkExpired and Assignments/Confirm. -->
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
                    <AppIcon :name="content.icon" class="h-6 w-6 text-amber-600" />
                </div>

                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">{{ content.title }}</h1>

                <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ content.body }}</p>
                <p v-if="content.hint" class="mt-3 text-sm leading-relaxed text-slate-600">{{ content.hint }}</p>

                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <BaseButton href="/" variant="primary" size="sm">Back to home</BaseButton>
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center text-sm font-semibold text-brand-700 hover:underline"
                    >
                        Go to my dashboard
                    </Link>
                </div>

                <!-- Small and last: useful when someone reports the problem, but
                     not the first thing a worried member reads. -->
                <p class="mt-6 text-xs text-slate-400">Error {{ status }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
