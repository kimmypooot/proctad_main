<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array, required: true }, // [{ label, value }]
    color: { type: String, default: '#2A338F' },
});

const showTable = ref(false);
const hoverIndex = ref(null);

const width = 720;
const height = 240;
const padding = { top: 24, right: 12, bottom: 28, left: 12 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const niceMax = computed(() => {
    const max = Math.max(...props.items.map((i) => i.value), 1);
    if (max <= 5) return 5;
    const magnitude = 10 ** Math.floor(Math.log10(max));
    return Math.ceil(max / magnitude) * magnitude;
});

const bandWidth = computed(() => plotWidth / Math.max(props.items.length, 1));
const barWidth = computed(() => Math.min(bandWidth.value * 0.55, 40));

const bars = computed(() => props.items.map((item, i) => {
    const barHeight = (item.value / niceMax.value) * plotHeight;
    const x = padding.left + bandWidth.value * i + (bandWidth.value - barWidth.value) / 2;
    const y = padding.top + plotHeight - barHeight;
    return { ...item, x, y, barHeight, cx: x + barWidth.value / 2 };
}));
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
                    <th class="py-1.5">Field Office</th>
                    <th class="py-1.5 text-right">Members</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="item in items" :key="item.label">
                    <td class="py-1.5 text-slate-700">{{ item.label }}</td>
                    <td class="py-1.5 text-right font-medium text-slate-900">{{ item.value.toLocaleString() }}</td>
                </tr>
            </tbody>
        </table>

        <svg v-else :viewBox="`0 0 ${width} ${height}`" class="mt-2 h-auto w-full" role="img" :aria-label="title">
            <rect
                v-for="(bar, i) in bars"
                :key="`hit-${bar.label}`"
                :x="padding.left + bandWidth * i"
                :y="padding.top"
                :width="bandWidth"
                :height="plotHeight"
                fill="transparent"
                @mouseenter="hoverIndex = i"
                @mouseleave="hoverIndex = null"
            />

            <rect
                v-for="bar in bars"
                :key="bar.label"
                :x="bar.x"
                :y="bar.y"
                :width="barWidth"
                :height="Math.max(bar.barHeight, 1)"
                :fill="color"
                :fill-opacity="hoverIndex === null || bars.indexOf(bar) === hoverIndex ? 1 : 0.55"
                rx="4"
            />

            <text
                v-for="bar in bars"
                :key="`v-${bar.label}`"
                :x="bar.cx"
                :y="bar.y - 6"
                text-anchor="middle"
                font-size="11"
                font-weight="600"
                fill="#0b0b0b"
            >
                {{ bar.value.toLocaleString() }}
            </text>

            <text
                v-for="bar in bars"
                :key="`l-${bar.label}`"
                :x="bar.cx"
                :y="height - 8"
                text-anchor="middle"
                font-size="11"
                fill="#898781"
            >
                {{ bar.label }}
            </text>
        </svg>
    </div>
</template>
