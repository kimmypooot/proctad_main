<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * The canonical app surface: `rounded-xl border border-slate-200 bg-white`
 * (the "surface" radius from app.css §Design system conventions). This string
 * was hand-repeated ~127 times across the dashboard app; BaseCard is the single
 * home for it so panels, list cards, and section wrappers stay in lockstep.
 *
 * Padding is a prop rather than baked in so callers that need a flush surface
 * (e.g. a card that only wraps a table, or one with its own header/footer bands)
 * can opt out. Extra classes passed by the caller fall through to the root.
 */
const props = defineProps({
    padding: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
    },
    /** Adds the interactive hover treatment used by linkable cards. Implied when `href` is set. */
    hover: { type: Boolean, default: false },
    /** When set, the whole card becomes an Inertia link (mirrors StatCard). */
    href: { type: String, default: null },
    /** Use a plain <a> for external/download links instead of an Inertia visit. */
    external: { type: Boolean, default: false },
});

const paddings = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
};

const tag = computed(() => {
    if (!props.href) return 'div';
    return props.external ? 'a' : Link;
});

const isInteractive = computed(() => props.hover || props.href !== null);

const classes = computed(() => [
    'rounded-xl border border-slate-200 bg-white',
    paddings[props.padding],
    isInteractive.value ? 'transition-shadow duration-200 hover:border-brand-300 hover:shadow-md' : '',
    props.href ? 'block' : '',
]);
</script>

<template>
    <component :is="tag" :href="href ?? undefined" :class="classes">
        <div v-if="$slots.header" class="mb-4 border-b border-slate-100 pb-4">
            <slot name="header" />
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-4 border-t border-slate-100 pt-4">
            <slot name="footer" />
        </div>
    </component>
</template>
