<script setup>
import { useToasts } from '@/Composables/useToasts';
import AppIcon from './AppIcon.vue';

const { toasts, dismiss } = useToasts();

const config = {
    info: { icon: 'information-circle', classes: 'bg-brand-50 border-brand-200 text-brand-800', iconColor: 'text-brand-600' },
    success: { icon: 'check-circle', classes: 'bg-emerald-50 border-emerald-200 text-emerald-800', iconColor: 'text-emerald-600' },
    warning: { icon: 'exclamation-triangle', classes: 'bg-amber-50 border-amber-200 text-amber-800', iconColor: 'text-amber-600' },
    error: { icon: 'x-circle', classes: 'bg-accent-50 border-accent-200 text-accent-800', iconColor: 'text-accent-600' },
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex flex-col items-end gap-2 p-4 sm:inset-x-auto sm:top-20 sm:right-4">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border p-4 shadow-lg sm:w-96"
                    :class="config[toast.variant]?.classes ?? config.info.classes"
                    role="alert"
                >
                    <AppIcon
                        :name="config[toast.variant]?.icon ?? config.info.icon"
                        class="h-5 w-5 shrink-0"
                        :class="config[toast.variant]?.iconColor ?? config.info.iconColor"
                    />
                    <p class="min-w-0 flex-1 text-sm leading-relaxed">{{ toast.message }}</p>
                    <button
                        type="button"
                        class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
                        aria-label="Dismiss"
                        @click="dismiss(toast.id)"
                    >
                        <AppIcon name="x-mark" class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
