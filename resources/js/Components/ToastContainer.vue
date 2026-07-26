<script setup>
import { computed } from 'vue';
import { useToasts } from '@/Composables/useToasts';
import AppIcon from './AppIcon.vue';

const { toasts, dismiss, pause, resume } = useToasts();

/**
 * 'bottom' keeps toasts clear of the top of the screen. The public scanner
 * needs it: its scan verdict and sync status both sit at the top, and toasts
 * landing over them hide exactly what the operator is looking at.
 */
const props = defineProps({
    position: { type: String, default: 'top' },
});

const placement = computed(() => (props.position === 'bottom'
    ? 'bottom-4 sm:bottom-4 sm:right-4'
    : 'top-20 sm:top-20 sm:right-4'));

const config = {
    info: { icon: 'information-circle', classes: 'bg-brand-50 border-brand-200 text-brand-800', iconColor: 'text-brand-600' },
    success: { icon: 'check-circle', classes: 'bg-emerald-50 border-emerald-200 text-emerald-800', iconColor: 'text-emerald-600' },
    warning: { icon: 'exclamation-triangle', classes: 'bg-amber-50 border-amber-200 text-amber-800', iconColor: 'text-amber-600' },
    error: { icon: 'x-circle', classes: 'bg-accent-50 border-accent-200 text-accent-800', iconColor: 'text-accent-600' },
};

/**
 * Only errors interrupt. `role="alert"` is assertive — it cuts a screen reader
 * off mid-sentence, which is right for "Save failed" and actively hostile for
 * "Member saved". Everything else announces politely, at the next pause.
 *
 * The roles sit on the toasts rather than on the container: a container-level
 * live region wrapping elements that carry their own roles gets announced twice
 * by several screen readers.
 */
const roleFor = (variant) => (variant === 'error' ? 'alert' : 'status');
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 z-50 flex flex-col items-end gap-2 p-4 sm:inset-x-auto"
            :class="placement"
        >
            <TransitionGroup :name="position === 'bottom' ? 'toast-up' : 'toast'">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border p-4 shadow-lg sm:w-96"
                    :class="config[toast.variant]?.classes ?? config.info.classes"
                    :role="roleFor(toast.variant)"
                    @mouseenter="pause(toast.id)"
                    @mouseleave="resume(toast.id)"
                    @focusin="pause(toast.id)"
                    @focusout="resume(toast.id)"
                >
                    <AppIcon
                        :name="config[toast.variant]?.icon ?? config.info.icon"
                        class="h-5 w-5 shrink-0"
                        :class="config[toast.variant]?.iconColor ?? config.info.iconColor"
                    />
                    <p class="min-w-0 flex-1 text-sm leading-relaxed">{{ toast.message }}</p>
                    <button
                        type="button"
                        class="-my-1 -mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md opacity-60 transition-opacity hover:opacity-100"
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

/* Bottom-anchored toasts slide up from the edge they sit on. */
.toast-up-enter-active,
.toast-up-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-up-enter-from,
.toast-up-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
