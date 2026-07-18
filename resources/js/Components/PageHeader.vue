<script setup>
import Breadcrumbs from './Breadcrumbs.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    /** Breadcrumb items: [{ label, href? }] */
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="relative overflow-hidden text-white">
        <!-- CSC facade background image -->
        <div class="absolute inset-0 bg-brand-900" aria-hidden="true">
            <img
                :src="'/images/cscbg_facade.jpeg'"
                alt=""
                class="h-full w-full object-cover opacity-25"
                aria-hidden="true"
            />
        </div>
        <!-- Dark overlay for readability -->
        <div class="absolute inset-0 bg-gradient-to-b from-brand-900/80 via-brand-900/70 to-brand-900/85" aria-hidden="true" />

        <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
            <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="mb-6" />
            <h1 class="max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl animate-fade-up">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-4 max-w-2xl text-base leading-relaxed text-brand-100 sm:text-lg animate-fade-up">
                {{ description }}
            </p>
        </div>
    </header>
</template>
