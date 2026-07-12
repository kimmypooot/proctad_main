<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div v-if="$slots.back" class="mb-1">
                <slot name="back" />
            </div>
            <p v-if="$slots.eyebrow" class="font-mono text-sm font-semibold text-brand-700">
                <slot name="eyebrow" />
            </p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900" :class="$slots.eyebrow && 'mt-1'">
                {{ title }}
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-slate-500">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
            <div v-if="$slots.badges" class="mt-2 flex flex-wrap items-center gap-2">
                <slot name="badges" />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
