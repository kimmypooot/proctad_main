<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    color: { type: String, default: '#2A338F' },
    valueSuffix: { type: String, default: '' },
});

const svgEl = ref(null);
const hoverIndex = ref(null);
const showTable = ref(false);

const width = 720;
const height = 240;
const padding = { top: 16, right: 16, bottom: 28, left: 40 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const niceMax = computed(() => {
    const max = Math.max(...props.values, 1);
    if (max <= 5) return 5;
    const magnitude = 10 ** Math.floor(Math.log10(max));
    return Math.ceil(max / magnitude) * magnitude;
});

const xFor = (i) => padding.left + (props.labels.length > 1 ? (i / (props.labels.length - 1)) * plotWidth : plotWidth / 2);
const yFor = (v) => padding.top + plotHeight - (v / niceMax.value) * plotHeight;

const points = computed(() => props.values.map((v, i) => ({ x: xFor(i), y: yFor(v), label: props.labels[i], value: v })));

const linePath = computed(() => points.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' '));
const areaPath = computed(() => {
    if (!points.value.length) return '';
    const base = padding.top + plotHeight;
    return `${linePath.value} L${points.value[points.value.length - 1].x},${base} L${points.value[0].x},${base} Z`;
});

const gridLines = computed(() => [0, 0.25, 0.5, 0.75, 1].map((t) => ({
    y: padding.top + plotHeight * (1 - t),
    label: Math.round(niceMax.value * t).toLocaleString(),
})));

const hovered = computed(() => (hoverIndex.value !== null ? points.value[hoverIndex.value] : null));

const onMouseMove = (event) => {
    if (!svgEl.value || !points.value.length) return;
    const rect = svgEl.value.getBoundingClientRect();
    const fraction = (event.clientX - rect.left) / rect.width;
    const x = fraction * width;
    let nearest = 0;
    let nearestDist = Infinity;
    points.value.forEach((p, i) => {
        const dist = Math.abs(p.x - x);
        if (dist < nearestDist) {
            nearestDist = dist;
            nearest = i;
        }
    });
    hoverIndex.value = nearest;
};

const tooltipX = computed(() => {
    if (!hovered.value) return 0;
    return Math.min(Math.max(hovered.value.x, padding.left + 60), width - padding.right - 60);
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
            <button type="button" class="text-xs font-medium text-brand-700 hover:underline" @click="showTable = !showTable">
                {{ showTable ? 'View chart' : 'View as table' }}
            </button>
        </div>

        <table v-if="showTable" class="mt-4 w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="py-1.5">Period</th>
                    <th class="py-1.5 text-right">Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="p in points" :key="p.label">
                    <td class="py-1.5 text-slate-700">{{ p.label }}</td>
                    <td class="py-1.5 text-right font-medium text-slate-900">{{ p.value.toLocaleString() }}{{ valueSuffix }}</td>
                </tr>
            </tbody>
        </table>

        <svg
            v-else
            ref="svgEl"
            :viewBox="`0 0 ${width} ${height}`"
            class="mt-2 h-auto w-full"
            role="img"
            :aria-label="title"
            @mousemove="onMouseMove"
            @mouseleave="hoverIndex = null"
        >
            <line
                v-for="g in gridLines"
                :key="g.y"
                :x1="padding.left"
                :x2="width - padding.right"
                :y1="g.y"
                :y2="g.y"
                stroke="#e1e0d9"
                stroke-width="1"
            />
            <text v-for="g in gridLines" :key="`t-${g.y}`" :x="padding.left - 8" :y="g.y + 3" text-anchor="end" font-size="11" fill="#898781">
                {{ g.label }}
            </text>

            <path :d="areaPath" :fill="color" fill-opacity="0.1" stroke="none" />
            <path :d="linePath" fill="none" :stroke="color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

            <circle
                v-for="(p, i) in points"
                :key="p.label"
                :cx="p.x"
                :cy="p.y"
                :r="hoverIndex === i ? 6 : 4"
                :fill="color"
                stroke="#fcfcfb"
                stroke-width="2"
            />

            <text v-for="(p, i) in points" :key="`x-${i}`" :x="p.x" :y="height - 8" text-anchor="middle" font-size="11" fill="#898781">
                {{ p.label }}
            </text>

            <line
                v-if="hovered"
                :x1="hovered.x"
                :x2="hovered.x"
                :y1="padding.top"
                :y2="padding.top + plotHeight"
                stroke="#c3c2b7"
                stroke-width="1"
                stroke-dasharray="3,3"
            />

            <g v-if="hovered" :transform="`translate(${tooltipX}, ${padding.top})`">
                <rect x="-52" y="-4" width="104" height="34" rx="6" fill="#0b0b0b" fill-opacity="0.85" />
                <text x="0" y="10" text-anchor="middle" font-size="11" fill="#ffffff" font-weight="600">{{ hovered.label }}</text>
                <text x="0" y="23" text-anchor="middle" font-size="11" fill="#ffffff">{{ hovered.value.toLocaleString() }}{{ valueSuffix }}</text>
            </g>
        </svg>
    </div>
</template>
