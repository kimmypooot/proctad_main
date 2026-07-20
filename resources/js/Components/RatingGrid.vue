<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Array, required: true });

const props = defineProps({
    title: { type: String, default: null },
    statements: { type: Array, required: true },
    error: { type: [String, Array], default: null },
    scale: {
        type: Array,
        default: () => [5, 4, 3, 2, 1],
    },
    /** { 5: '5 - Excellent', … } from EvaluationCriteria::RATING_SCALE. Without
     *  it the grid shows bare numbers and the respondent has to guess whether 5
     *  is best — the labels existed in the payload but were never rendered. */
    scaleLabels: { type: Object, default: null },
});

const name = useId();

const errorFor = (index) => (Array.isArray(props.error) ? props.error[index] : null);

/** '5 - Excellent' -> 'Excellent', so headers stay narrow but meaningful. */
const wordFor = (n) => props.scaleLabels?.[n]?.replace(/^\s*\d+\s*[-–]\s*/, '') ?? null;
const labelFor = (n) => props.scaleLabels?.[n] ?? `${n}`;
</script>

<template>
    <fieldset>
        <legend v-if="title" class="text-sm font-semibold uppercase tracking-wide text-brand-700">{{ title }}</legend>

        <div class="mt-3 hidden grid-cols-[1fr_repeat(5,3rem)] gap-2 px-1 text-center text-xs font-medium text-slate-400 sm:grid">
            <span></span>
            <span v-for="n in scale" :key="n" class="leading-tight">
                {{ n }}
                <span v-if="wordFor(n)" class="mt-0.5 block text-[0.625rem] font-normal text-slate-400">{{ wordFor(n) }}</span>
            </span>
        </div>

        <div class="mt-1 divide-y divide-slate-100 rounded-lg border border-slate-200">
            <div v-for="(statement, index) in statements" :key="index" class="p-3 sm:grid sm:grid-cols-[1fr_repeat(5,3rem)] sm:items-center sm:gap-2">
                <p class="text-sm leading-relaxed text-slate-700">{{ statement }}</p>
                <div class="mt-2 flex justify-between gap-2 sm:mt-0 sm:contents">
                    <!-- The number alone is ambiguous on mobile, where the header
                         row is hidden, so the word travels with each option. -->
                    <label v-for="n in scale" :key="n" class="flex flex-1 flex-col items-center gap-1 sm:contents">
                        <span class="text-xs font-medium text-slate-500 sm:hidden">{{ n }}</span>
                        <span v-if="wordFor(n)" class="text-center text-[0.625rem] leading-tight text-slate-400 sm:hidden">
                            {{ wordFor(n) }}
                        </span>
                        <input
                            type="radio"
                            :name="`${name}-${index}`"
                            :value="n"
                            :checked="model[index] === n"
                            :aria-label="`${statement} — ${labelFor(n)}`"
                            class="mx-auto h-5 w-5 accent-brand-600 focus:ring-2 focus:ring-brand-100"
                            @change="model[index] = n"
                        >
                    </label>
                </div>
                <p v-if="errorFor(index)" class="col-span-full mt-1 text-xs text-accent-600" role="alert">
                    {{ errorFor(index) }}
                </p>
            </div>
        </div>

        <p v-if="typeof error === 'string'" class="mt-1.5 text-sm text-accent-600" role="alert">{{ error }}</p>
    </fieldset>
</template>
