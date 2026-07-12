<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    icon: { type: String, default: 'newspaper' },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-16 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600">
            <AppIcon :name="icon" class="h-7 w-7" />
        </span>
        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="description" class="mt-2 max-w-sm text-sm leading-relaxed text-slate-500">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
