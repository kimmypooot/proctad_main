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
        <nav aria-label="Progress" class="overflow-x-auto">
            <ol class="flex min-w-max items-center gap-2 sm:gap-4">
                <li v-for="(step, index) in steps" :key="step.key" class="flex items-center gap-2 sm:gap-4">
                    <button
                        type="button"
                        class="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition-colors"
                        :class="model === step.key ? 'bg-brand-50' : 'hover:bg-slate-50'"
                        @click="model = step.key"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                            :class="[
                                model === step.key
                                    ? 'bg-brand-600 text-white'
                                    : step.complete
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200',
                            ]"
                        >
                            <AppIcon v-if="step.complete && model !== step.key" name="check" class="h-3.5 w-3.5" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span>
                            <span
                                class="block text-sm font-semibold"
                                :class="model === step.key ? 'text-brand-800' : 'text-slate-700'"
                            >
                                {{ step.label }}
                            </span>
                            <span v-if="step.description" class="hidden text-xs text-slate-400 sm:block">
                                {{ step.description }}
                            </span>
                        </span>
                    </button>
                    <span v-if="index < steps.length - 1" class="h-px w-6 shrink-0 bg-slate-200 sm:w-10" aria-hidden="true" />
                </li>
            </ol>
        </nav>

        <p v-if="activeStep?.hint" class="mt-3 rounded-lg border border-brand-100 bg-brand-50/60 px-3 py-2 text-xs leading-relaxed text-brand-800">
            {{ activeStep.hint }}
        </p>
    </div>
</template>
