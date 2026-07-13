<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import Tooltip from './Tooltip.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: null },
    maxWidth: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);
const panel = ref(null);

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) emit('close');
};

watch(
    () => props.show,
    (show) => {
        document.body.style.overflow = show ? 'hidden' : '';
        if (show) {
            document.addEventListener('keydown', onKeydown);
            requestAnimationFrame(() => panel.value?.focus());
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/60" aria-hidden="true" @click="emit('close')" />

                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title ?? 'Dialog'"
                    tabindex="-1"
                    class="relative w-full rounded-xl bg-white shadow-xl animate-fade-up"
                    :class="widths[maxWidth]"
                >
                    <div v-if="title" class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                        <Tooltip text="Close" position="bottom">
                            <button
                                type="button"
                                class="rounded-md p-1 text-slate-400 transition-colors hover:text-slate-600"
                                aria-label="Close dialog"
                                @click="emit('close')"
                            >
                                <AppIcon name="x-mark" class="h-5 w-5" />
                            </button>
                        </Tooltip>
                    </div>
                    <div class="px-6 py-5">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
