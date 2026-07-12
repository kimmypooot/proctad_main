<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    variant: {
        type: String,
        default: 'info',
        validator: (v) => ['info', 'success', 'warning', 'error'].includes(v),
    },
    title: { type: String, default: null },
    dismissible: { type: Boolean, default: false },
});

const emit = defineEmits(['dismiss']);

const config = {
    info: { icon: 'information-circle', classes: 'bg-brand-50 border-brand-200 text-brand-800', iconColor: 'text-brand-600' },
    success: { icon: 'check-circle', classes: 'bg-emerald-50 border-emerald-200 text-emerald-800', iconColor: 'text-emerald-600' },
    warning: { icon: 'exclamation-triangle', classes: 'bg-amber-50 border-amber-200 text-amber-800', iconColor: 'text-amber-600' },
    error: { icon: 'x-circle', classes: 'bg-accent-50 border-accent-200 text-accent-800', iconColor: 'text-accent-600' },
};
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-lg border p-4 animate-fade-in"
        :class="config[variant].classes"
        role="alert"
    >
        <AppIcon :name="config[variant].icon" class="h-5 w-5 shrink-0" :class="config[variant].iconColor" />
        <div class="min-w-0 flex-1 text-sm">
            <p v-if="title" class="font-semibold">{{ title }}</p>
            <div :class="{ 'mt-1': title }" class="leading-relaxed">
                <slot />
            </div>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
            aria-label="Dismiss"
            @click="emit('dismiss')"
        >
            <AppIcon name="x-mark" class="h-4 w-4" />
        </button>
    </div>
</template>
