<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: String, required: true });

const props = defineProps({
    /** [{ key, label, description?, hint?, complete?: boolean }] */
    steps: { type: Array, required: true },
});

const activeStep = computed(() => props.steps.find((step) => step.key === model.value));
</script>

<template>
    <div>
        <!-- Step indicator -->
        <nav role="tablist" aria-label="Examination workflow steps" class="flex gap-1 overflow-x-auto border-b border-slate-200">
            <button
                v-for="step in steps"
                :key="step.key"
                type="button"
                role="tab"
                :aria-selected="model === step.key"
                class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                :class="model === step.key
                    ? 'border-brand-600 text-brand-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                @click="model = step.key"
            >
                <AppIcon v-if="step.complete && model !== step.key" name="check" class="h-3.5 w-3.5 text-emerald-600" />
                {{ step.label }}
            </button>
        </nav>

        <p v-if="activeStep?.hint" class="mt-3 rounded-lg border border-brand-100 bg-brand-50/60 px-3 py-2 text-xs leading-relaxed text-brand-800">
            {{ activeStep.hint }}
        </p>
    </div>
</template>
