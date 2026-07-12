<script setup>
import { useId } from 'vue';

const model = defineModel({ type: File, default: null });

defineProps({
    label: { type: String, required: true },
    accept: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
});

const id = useId();
const onChange = (event) => {
    model.value = event.target.files[0] ?? null;
};
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-accent-600" aria-hidden="true">*</span>
            <span v-if="optional" class="font-normal text-slate-400">(optional)</span>
        </label>
        <input
            :id="id"
            type="file"
            :accept="accept"
            class="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
            :aria-invalid="!!error || undefined"
            :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
            @change="onChange"
        >
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-slate-500">{{ hint }}</p>
    </div>
</template>
