<script setup>
import AppIcon from './AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: null },
    icon: { type: String, default: null },
    hint: { type: String, default: null },
    /** Set false for a stat rendered as a plain grid cell inside a shared bordered container (e.g. a stats row within a card). */
    bordered: { type: Boolean, default: true },
    /** Single-row layout: icon on the left, label + value stacked to its right. */
    compact: { type: Boolean, default: false },
    /** Tints the icon chip and top accent bar. Purely decorative — doesn't change meaning. */
    accent: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'emerald', 'amber', 'accent', 'slate'].includes(v),
    },
});

const chipClasses = {
    brand: 'bg-brand-50 text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    accent: 'bg-accent-50 text-accent-600',
    slate: 'bg-slate-100 text-slate-500',
};

const barClasses = {
    brand: 'bg-brand-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    accent: 'bg-accent-500',
    slate: 'bg-slate-300',
};
</script>

<template>
    <div v-if="compact" class="flex items-center gap-3" :class="bordered ? 'rounded-xl border border-slate-200 bg-white p-4' : ''">
        <span v-if="icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="chipClasses[accent]">
            <AppIcon :name="icon" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</p>
            <p class="text-lg font-bold tracking-tight text-slate-900">
                <slot>{{ value }}</slot>
            </p>
        </div>
    </div>

    <div
        v-else
        class="group relative overflow-hidden"
        :class="bordered ? 'rounded-xl border border-slate-200 bg-white p-5 transition-shadow duration-200 hover:shadow-md' : ''"
    >
        <span v-if="bordered" class="absolute inset-x-0 top-0 h-1" :class="barClasses[accent]" aria-hidden="true" />
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</p>
            <span
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-105"
                :class="chipClasses[accent]"
            >
                <AppIcon :name="icon" class="h-5 w-5" />
            </span>
        </div>
        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
            <slot>{{ value }}</slot>
        </p>
        <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
    </div>
</template>
