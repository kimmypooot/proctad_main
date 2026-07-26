<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import Tooltip from './Tooltip.vue';

/**
 * Row/card action (Edit, Remove, Download, ...) — compact icon-only from `md` up,
 * where the hover tooltip can carry the label, and icon + visible text below that.
 *
 * The responsive label is the whole point: a tooltip fires on hover and focus,
 * and a touchscreen has neither. Without it a phone user sees a bare pencil next
 * to a bare trash can and finds out which is which by tapping one. `aria-label`
 * covers screen readers and does nothing for the sighted majority.
 *
 * Desktop tables are wrapped in `hidden md:block`, so their buttons are never
 * rendered at a width where the label would show — only the `md:hidden` card
 * fallbacks and the few tables that scroll horizontally on mobile pick it up.
 */
const props = defineProps({
    icon: { type: String, required: true },
    /** Tooltip text, accessible name, and the visible label below `md`. */
    label: { type: String, required: true },
    variant: { type: String, default: 'default', validator: (v) => ['default', 'danger'].includes(v) },
    tooltipPosition: { type: String, default: 'top', validator: (v) => ['top', 'bottom'].includes(v) },
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /**
     * Stay a square glyph at every width. For actions sitting in a tight inline
     * row where the label has nowhere to go and the surrounding copy already
     * says what the control does.
     */
    iconOnly: { type: Boolean, default: false },
});

const variants = {
    default: 'text-slate-500 hover:bg-slate-100 hover:text-brand-700',
    danger: 'text-slate-500 hover:bg-accent-50 hover:text-accent-600',
};

// 40px tall on touch, collapsing to the compact 32px square from `md` up. Three
// 32px targets in a row are hard to hit with a thumb, and the destructive one
// sits right beside the benign ones — the miss is expensive.
const classes = computed(() => [
    'inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg transition-colors duration-150',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
    'disabled:pointer-events-none disabled:opacity-40',
    'md:h-8 md:w-8 md:gap-0 md:px-0',
    props.iconOnly ? 'w-10' : 'px-3 text-sm font-medium',
    variants[props.variant],
]);

const tag = computed(() => {
    if (!props.href) return 'button';
    return props.external ? 'a' : Link;
});
</script>

<template>
    <Tooltip :text="label" :position="tooltipPosition">
        <component
            :is="tag"
            :href="href ?? undefined"
            :type="href ? undefined : 'button'"
            :disabled="href ? undefined : disabled"
            :aria-label="label"
            :class="classes"
        >
            <AppIcon :name="icon" class="h-4 w-4 shrink-0" />
            <span v-if="!iconOnly" class="md:hidden">{{ label }}</span>
        </component>
    </Tooltip>
</template>
