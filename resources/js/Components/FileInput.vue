<script setup>
import { ref, useId } from 'vue';
import { compressImage } from '@/Utils/compressImage';

const model = defineModel({ type: File, default: null });

const props = defineProps({
    label: { type: String, required: true },
    /** The form field key — see the note on TextInput's `name`. */
    name: { type: String, default: null },
    accept: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    // Opt-in: downscale + re-encode image selections before upload. Used for
    // ID-photo fields where a raw phone photo is far larger than needed.
    compress: { type: Boolean, default: false },
});

const id = useId();
const optimizing = ref(false);

const onChange = async (event) => {
    const file = event.target.files[0] ?? null;

    if (file && props.compress) {
        optimizing.value = true;
        try {
            model.value = await compressImage(file);
        } finally {
            optimizing.value = false;
        }
        return;
    }

    model.value = file;
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
            :name="name ?? undefined"
            type="file"
            :accept="accept"
            class="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
            :aria-invalid="!!error || undefined"
            :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
            @change="onChange"
        >
        <p v-if="optimizing" class="mt-1.5 flex items-center gap-1.5 text-sm text-brand-600" role="status" aria-live="polite">
            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z" />
            </svg>
            Optimizing photo…
        </p>
        <p v-else-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-slate-500">{{ hint }}</p>
    </div>
</template>
