<script setup>
import { ref, useId } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    /** Array of { question, answer } */
    items: { type: Array, required: true },
});

const uid = useId();
const openIndex = ref(null);

const toggle = (index) => {
    openIndex.value = openIndex.value === index ? null : index;
};

// Height-auto transitions for smooth expand/collapse
const onEnter = (el) => {
    el.style.height = '0';
    requestAnimationFrame(() => {
        el.style.height = `${el.scrollHeight}px`;
    });
};
const onAfterEnter = (el) => {
    el.style.height = 'auto';
};
const onLeave = (el) => {
    el.style.height = `${el.scrollHeight}px`;
    requestAnimationFrame(() => {
        el.style.height = '0';
    });
};
</script>

<template>
    <div class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
        <div v-for="(item, index) in items" :key="index">
            <h3>
                <button
                    :id="`${uid}-trigger-${index}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors duration-200 hover:bg-slate-50 sm:px-6 sm:py-5"
                    :aria-expanded="openIndex === index"
                    :aria-controls="`${uid}-panel-${index}`"
                    @click="toggle(index)"
                >
                    <span class="text-sm font-semibold text-slate-900 sm:text-base">
                        {{ item.question }}
                    </span>
                    <AppIcon
                        name="chevron-down"
                        class="h-5 w-5 shrink-0 text-brand-600 transition-transform duration-200"
                        :class="{ 'rotate-180': openIndex === index }"
                    />
                </button>
            </h3>

            <Transition
                enter-active-class="overflow-hidden transition-[height] duration-300 ease-out"
                leave-active-class="overflow-hidden transition-[height] duration-300 ease-out"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @leave="onLeave"
            >
                <div
                    v-show="openIndex === index"
                    :id="`${uid}-panel-${index}`"
                    role="region"
                    :aria-labelledby="`${uid}-trigger-${index}`"
                >
                    <p class="px-5 pb-5 text-sm leading-relaxed text-slate-600 sm:px-6 sm:pb-6">
                        {{ item.answer }}
                    </p>
                </div>
            </Transition>
        </div>
    </div>
</template>
