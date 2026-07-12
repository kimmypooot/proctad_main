<script setup>
import { computed, useId } from 'vue';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    rows: { type: Number, default: 4 },
    placeholder: { type: String, default: null },
});

const id = useId();
const describedBy = computed(() => {
    if (props.error) return `${id}-error`;
    if (props.hint) return `${id}-hint`;
    return undefined;
});
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-accent-600" aria-hidden="true">*</span>
            <span v-if="optional" class="font-normal text-slate-400">(optional)</span>
        </label>

        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :required="required"
            :placeholder="placeholder ?? undefined"
            :aria-invalid="!!error || undefined"
            :aria-describedby="describedBy"
            class="block w-full rounded-lg border bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors duration-200 focus:outline-none focus:ring-2"
            :class="error
                ? 'border-accent-400 focus:border-accent-500 focus:ring-accent-200'
                : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100'"
        />

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-slate-500">
            {{ hint }}
        </p>
    </div>
</template>
