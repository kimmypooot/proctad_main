<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'accent', 'outline', 'ghost', 'white', 'link', 'link-accent'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    type: { type: String, default: 'button' },
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    /**
     * Optional leading AppIcon name. Purely decorative: the label still carries
     * the meaning, so it is `aria-hidden` and a button must never rely on the
     * icon alone to say what it does (that is IconButton's job, and it takes a
     * required label for exactly this reason).
     *
     * Suppressed while loading — the spinner occupies the same slot, and
     * showing both makes the button jump a few pixels wider mid-submit.
     */
    icon: { type: String, default: null },
});

/**
 * Hover feedback is colour + elevation, never geometry. `primary`/`accent`
 * previously carried a `hover:scale-[1.02]` that the other four variants did
 * not — so buttons sitting side by side in one row behaved differently — and
 * scaling a text button re-rasterizes its label, which reads as a blur rather
 * than as feedback. Elevation is the convention public-sector design systems
 * settle on for the same reason.
 */
const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 shadow-sm hover:shadow-md',
    secondary: 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
    accent: 'bg-accent-500 text-white hover:bg-accent-600 active:bg-accent-700 shadow-sm hover:shadow-md',
    outline: 'border border-slate-300 bg-white text-slate-700 hover:border-brand-400 hover:text-brand-700 active:bg-slate-50',
    ghost: 'text-brand-700 hover:bg-brand-50 active:bg-brand-100',
    white: 'bg-white text-brand-700 hover:bg-brand-50 active:bg-brand-100 shadow-sm',
};

const sizes = {
    sm: 'px-4 py-2 text-sm min-h-[2.5rem]',
    md: 'px-6 py-3 text-sm min-h-[2.75rem]',
    lg: 'px-8 py-3.5 text-base min-h-[3rem]',
};

/**
 * `link`/`link-accent`: an inline text action (e.g. a table row's "Edit" /
 * "Remove"), not a button — no padding, background, or min-height, matching
 * the app's long-standing "text-sm font-medium ... hover:underline" convention.
 */
const isLink = computed(() => props.variant === 'link' || props.variant === 'link-accent');

const linkColors = {
    link: 'text-brand-700 hover:text-brand-800',
    'link-accent': 'text-accent-600 hover:text-accent-700',
};

const classes = computed(() => isLink.value
    ? [
        'inline-flex items-center gap-1.5 text-sm font-medium underline-offset-2 hover:underline',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
        'disabled:pointer-events-none disabled:opacity-60',
        linkColors[props.variant],
        props.block ? 'w-full' : '',
    ]
    : [
        // `transition` (not `transition-colors`) so the variants' hover elevation animates too.
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition duration-150',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
        'disabled:pointer-events-none disabled:opacity-60',
        variants[props.variant],
        sizes[props.size],
        props.block ? 'w-full' : '',
    ]);

const tag = computed(() => {
    if (!props.href) return 'button';
    return props.external ? 'a' : Link;
});
</script>

<template>
    <component
        :is="tag"
        :href="href ?? undefined"
        :type="href ? undefined : type"
        :disabled="href ? undefined : disabled || loading"
        :aria-busy="loading || undefined"
        :class="classes"
    >
        <LoadingSpinner v-if="loading" class="h-4 w-4" />
        <AppIcon
            v-else-if="icon"
            :name="icon"
            class="h-4 w-4 shrink-0"
            aria-hidden="true"
        />
        <slot />
    </component>
</template>
