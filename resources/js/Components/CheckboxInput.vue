<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Boolean, default: false });

defineProps({
    error: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const id = useId();
</script>

<template>
    <div>
        <label :for="id" class="flex items-start gap-3" :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'">
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                :disabled="disabled"
                :aria-invalid="!!error || undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-brand-600 accent-brand-600 focus:ring-2 focus:ring-brand-100"
            >
            <span class="text-sm leading-relaxed text-slate-600">
                <slot />
            </span>
        </label>
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">
            {{ error }}
        </p>
    </div>
</template>
