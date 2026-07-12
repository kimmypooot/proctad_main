<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    icon: { type: String, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <div class="flex items-center gap-2">
        <AppIcon :name="icon" class="h-5 w-5 text-brand-600" />
        <h2 class="text-base font-semibold text-slate-900">{{ label }}</h2>
    </div>
</template>
