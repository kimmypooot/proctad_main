<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    title: { type: String, required: true },
    segments: { type: Array, required: true }, // [{ key, label, value, color }]
});

const colorHex = {
    good: '#10b981',
    neutral: '#94a3b8',
    warning: '#f59e0b',
    critical: '#EC1C2D',
};

const iconFor = {
    good: 'check-circle',
    neutral: 'clock',
    warning: 'exclamation-triangle',
    critical: 'x-circle',
};

const total = computed(() => props.segments.reduce((sum, s) => sum + s.value, 0));

const widths = computed(() => {
    if (!total.value) return props.segments.map(() => 0);
    return props.segments.map((s) => (s.value / total.value) * 100);
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>

        <div v-if="total" class="mt-4 flex h-6 w-full overflow-hidden rounded-full bg-slate-100">
            <div
                v-for="(segment, i) in segments"
                :key="segment.key"
                class="h-full first:rounded-l-full last:rounded-r-full"
                :style="{
                    width: `${widths[i]}%`,
                    backgroundColor: colorHex[segment.color] ?? colorHex.neutral,
                    marginLeft: i > 0 && widths[i] > 0 ? '2px' : '0',
                }"
                :title="`${segment.label}: ${segment.value}`"
            />
        </div>
        <p v-else class="mt-4 text-sm text-slate-400">No members recorded yet.</p>

        <ul class="mt-4 grid grid-cols-2 gap-3">
            <li v-for="segment in segments" :key="segment.key" class="flex items-center gap-2 text-sm">
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                    :style="{ backgroundColor: `${colorHex[segment.color] ?? colorHex.neutral}1a`, color: colorHex[segment.color] ?? colorHex.neutral }"
                >
                    <AppIcon :name="iconFor[segment.color] ?? 'clock'" class="h-3.5 w-3.5" />
                </span>
                <span class="text-slate-600">{{ segment.label }}</span>
                <span class="ml-auto font-semibold text-slate-900">{{ segment.value.toLocaleString() }}</span>
            </li>
        </ul>
    </div>
</template>
