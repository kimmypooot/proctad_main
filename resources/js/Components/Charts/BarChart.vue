<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array, required: true }, // [{ label, value }]
    color: { type: String, default: '#2A338F' },
    // Heading for the label column in the "View as table" fallback.
    categoryLabel: { type: String, default: 'Category' },
    valueLabel: { type: String, default: 'Count' },
});

const showTable = ref(false);
const hoverIndex = ref(null);

const width = 720;
const height = 248;
// Room for two lines of axis label: the categories are place names
// ("Ormoc City, Leyte"), not the three-letter codes this started with.
const padding = { top: 24, right: 12, bottom: 40, left: 12 };
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

const labelFontSize = 11;

/**
 * SVG text does not wrap, so a label wider than its band silently overlaps its
 * neighbours. Fit it into at most two lines on word boundaries, measuring by an
 * average glyph width, and ellipsise whatever still does not fit.
 */
function labelLines(label) {
    const maxChars = Math.max(Math.floor(bandWidth.value / (labelFontSize * 0.55)), 4);

    if (label.length <= maxChars) return [label];

    const lines = [];
    let line = '';

    for (const word of label.split(/\s+/)) {
        const candidate = line ? `${line} ${word}` : word;

        if (candidate.length <= maxChars) {
            line = candidate;
            continue;
        }

        if (line) lines.push(line);
        line = word;

        if (lines.length === 2) break;
    }

    if (lines.length < 2 && line) lines.push(line);

    return lines.map((text) => (text.length > maxChars ? `${text.slice(0, maxChars - 1)}…` : text));
}

const bars = computed(() => props.items.map((item, i) => {
    const barHeight = (item.value / niceMax.value) * plotHeight;
    const x = padding.left + bandWidth.value * i + (bandWidth.value - barWidth.value) / 2;
    const y = padding.top + plotHeight - barHeight;
    return { ...item, x, y, barHeight, cx: x + barWidth.value / 2, lines: labelLines(item.label) };
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
                    <th class="py-1.5">{{ categoryLabel }}</th>
                    <th class="py-1.5 text-right">{{ valueLabel }}</th>
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
                :y="height - 8 - (bar.lines.length - 1) * 12"
                text-anchor="middle"
                :font-size="labelFontSize"
                fill="#898781"
            >
                <title>{{ bar.label }}</title>
                <tspan v-for="(line, i) in bar.lines" :key="line + i" :x="bar.cx" :dy="i === 0 ? 0 : 12">{{ line }}</tspan>
            </text>
        </svg>
    </div>
</template>
