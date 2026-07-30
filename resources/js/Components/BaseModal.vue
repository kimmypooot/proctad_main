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

// The element focused before the modal opened, so we can hand focus back when
// it closes — otherwise keyboard/screen-reader users are dumped at the top of
// the page with no idea where they were.
let previouslyFocused = null;

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
};

/** Currently focusable, visible children of the panel, in tab order. */
const focusableWithin = () => {
    if (!panel.value) return [];
    const selector = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled])',
        'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    return Array.from(panel.value.querySelectorAll(selector))
        .filter((el) => el.offsetParent !== null || el === document.activeElement);
};

const onKeydown = (e) => {
    if (!props.show) return;

    if (e.key === 'Escape') {
        emit('close');
        return;
    }

    // Focus trap: keep Tab / Shift+Tab cycling inside the dialog so focus never
    // lands on the page behind it while it's modal.
    if (e.key === 'Tab') {
        const focusable = focusableWithin();
        if (focusable.length === 0) {
            e.preventDefault();
            panel.value?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (e.shiftKey && (active === first || active === panel.value)) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
        }
    }
};

watch(
    () => props.show,
    (show) => {
        document.body.style.overflow = show ? 'hidden' : '';
        if (show) {
            previouslyFocused = document.activeElement;
            document.addEventListener('keydown', onKeydown);
            requestAnimationFrame(() => (focusableWithin()[0] ?? panel.value)?.focus());
        } else {
            document.removeEventListener('keydown', onKeydown);
            // Restore focus to whatever opened the modal.
            if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
            previouslyFocused = null;
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
            <div v-if="show" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
                <div class="fixed inset-0 bg-slate-900/60" aria-hidden="true" @click="emit('close')" />

                <!--
                    The panel is a flex column with a capped height: header and footer stay
                    pinned (`shrink-0`) while only the body scrolls. Without this a form
                    taller than the viewport bleeds off both edges and its footer — where
                    Save lives — becomes unreachable, because opening the modal also locks
                    body scroll. `dvh` rather than `vh` so collapsing mobile browser chrome
                    doesn't push the footer under the address bar.

                    Below `sm` it docks to the bottom as a sheet: the actions land under the
                    thumb, which is where they need to be for a form filled on a phone at a
                    testing venue.
                -->
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title ?? 'Dialog'"
                    tabindex="-1"
                    class="relative flex max-h-[92dvh] w-full flex-col rounded-t-xl bg-white shadow-xl animate-fade-up sm:max-h-[calc(100dvh-2rem)] sm:rounded-xl"
                    :class="widths[maxWidth]"
                >
                    <div v-if="title" class="flex shrink-0 items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                        <Tooltip text="Close" position="bottom">
                            <button
                                type="button"
                                class="-mr-2 inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-400 transition-colors hover:text-slate-600 sm:h-8 sm:w-8"
                                aria-label="Close dialog"
                                @click="emit('close')"
                            >
                                <AppIcon name="x-mark" class="h-5 w-5" />
                            </button>
                        </Tooltip>
                    </div>
                    <div class="flex-1 overflow-y-auto overscroll-contain px-6 py-5">
                        <slot />
                    </div>
                    <div
                        v-if="$slots.footer"
                        class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:pb-4"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
