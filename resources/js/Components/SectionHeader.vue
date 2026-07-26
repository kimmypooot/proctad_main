<script setup>
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
    align: { type: String, default: 'center' },
});
</script>

<template>
    <div class="reveal max-w-3xl" :class="align === 'center' ? 'mx-auto text-center' : 'text-left'">
        <p v-if="eyebrow" class="mb-3 text-sm font-semibold uppercase tracking-widest text-accent-600">
            {{ eyebrow }}
        </p>
        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            {{ title }}
        </h2>
        <p v-if="description" class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
            {{ description }}
        </p>
    </div>
</template>
