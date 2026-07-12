<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** 0-100 */
    percent: { type: Number, required: true },
    size: { type: Number, default: 64 },
    strokeWidth: { type: Number, default: 7 },
    /** Ring color when not complete. Switches to emerald automatically at 100%. */
    color: { type: String, default: 'brand' },
});

const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const clamped = computed(() => Math.min(100, Math.max(0, props.percent)));
const offset = computed(() => circumference.value * (1 - clamped.value / 100));

// Static class map — Tailwind's content scanner needs literal class strings,
// so this can't be built from a template-interpolated color name.
const strokeClasses = {
    brand: 'stroke-brand-500',
    accent: 'stroke-accent-500',
    amber: 'stroke-amber-500',
};

const strokeClass = computed(() => (clamped.value >= 100 ? 'stroke-emerald-500' : strokeClasses[props.color] ?? strokeClasses.brand));
</script>

<template>
    <div class="relative inline-flex shrink-0 items-center justify-center" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg :width="size" :height="size" class="-rotate-90">
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                class="stroke-slate-100"
                :stroke-width="strokeWidth"
            />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :class="strokeClass"
                :stroke-width="strokeWidth"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
                class="transition-[stroke-dashoffset] duration-500 ease-out"
            />
        </svg>
        <span class="absolute text-sm font-bold text-slate-900">{{ Math.round(clamped) }}%</span>
    </div>
</template>
