<script setup>
defineProps({
    variant: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'accent', 'success', 'warning', 'neutral'].includes(v),
    },
    /**
     * `xs`: tighter, solid-fill pill for space-constrained/printed contexts
     * (e.g. ID cards) — no ring border, smaller text.
     */
    size: {
        type: String,
        default: 'sm',
        validator: (v) => ['sm', 'xs'].includes(v),
    },
});

const variants = {
    sm: {
        brand: 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200',
        accent: 'bg-accent-50 text-accent-700 ring-1 ring-inset ring-accent-200',
        success: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        warning: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        neutral: 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200',
    },
    xs: {
        brand: 'bg-brand-100 text-brand-700',
        accent: 'bg-accent-100 text-accent-700',
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        neutral: 'bg-slate-200 text-slate-700',
    },
};

const sizes = {
    sm: 'gap-1.5 px-3 py-1 text-xs font-semibold',
    xs: 'gap-1 px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full"
        :class="[sizes[size], variants[size][variant]]"
    >
        <slot />
    </span>
</template>
