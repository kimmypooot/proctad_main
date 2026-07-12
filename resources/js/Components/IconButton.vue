<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import Tooltip from './Tooltip.vue';

/**
 * Icon-only row/card action (Edit, Remove, Download, ...) with a hover/focus
 * tooltip carrying the label — replaces the old plain-text "Edit" / "Remove"
 * links in table action columns across the app.
 */
const props = defineProps({
    icon: { type: String, required: true },
    /** Used as both the tooltip text and the accessible name. */
    label: { type: String, required: true },
    variant: { type: String, default: 'default', validator: (v) => ['default', 'danger'].includes(v) },
    tooltipPosition: { type: String, default: 'top', validator: (v) => ['top', 'bottom'].includes(v) },
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const variants = {
    default: 'text-slate-500 hover:bg-slate-100 hover:text-brand-700',
    danger: 'text-slate-500 hover:bg-accent-50 hover:text-accent-600',
};

const classes = computed(() => [
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors duration-150',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
    'disabled:pointer-events-none disabled:opacity-40',
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
            <AppIcon :name="icon" class="h-4 w-4" />
        </component>
    </Tooltip>
</template>
