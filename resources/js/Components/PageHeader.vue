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
    <header class="bg-brand-800 text-white">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
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
