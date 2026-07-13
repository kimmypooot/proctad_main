<script setup>
import { computed, ref, useId } from 'vue';
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    placeholder: { type: String, default: null },
    inputmode: { type: String, default: null },
    /** Extra classes for the `<input>` itself — plain `class` on this component lands on the outer wrapper instead. */
    inputClass: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const id = useId();
const showPassword = ref(false);

const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && showPassword.value ? 'text' : props.type));
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

        <div class="relative">
            <input
                :id="id"
                v-model="model"
                :type="inputType"
                :required="required"
                :disabled="disabled"
                :autocomplete="autocomplete ?? undefined"
                :placeholder="placeholder ?? undefined"
                :inputmode="inputmode ?? undefined"
                :aria-invalid="!!error || undefined"
                :aria-describedby="describedBy"
                class="block w-full rounded-lg border bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 min-h-[2.75rem] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
                :class="[
                    error
                        ? 'border-accent-400 focus:border-accent-500 focus:ring-accent-200'
                        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100',
                    isPassword ? 'pr-12' : '',
                    inputClass,
                ]"
            >

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition-colors hover:text-slate-600"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
            >
                <AppIcon :name="showPassword ? 'eye-slash' : 'eye'" class="h-5 w-5" />
            </button>
        </div>

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-slate-500">
            {{ hint }}
        </p>
    </div>
</template>
