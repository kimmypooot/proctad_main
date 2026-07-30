<script setup>
import { computed, useId } from 'vue';
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, required: true },
    /** The form field key — see the note on TextInput's `name`. */
    name: { type: String, default: null },
    /** Array of strings or { value, label } objects */
    options: { type: Array, default: () => [] },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Select an option' },
});

const id = useId();
const normalized = computed(() =>
    props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: o })),
);

/** When every option carries a `group`, render <optgroup>s in first-seen order; otherwise a flat list. */
const grouped = computed(() => {
    if (!normalized.value.length || !normalized.value.every((o) => o.group)) return null;

    const byGroup = new Map();
    for (const opt of normalized.value) {
        if (!byGroup.has(opt.group)) byGroup.set(opt.group, { label: opt.group_label ?? opt.group, options: [] });
        byGroup.get(opt.group).options.push(opt);
    }

    return [...byGroup.values()];
});
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
            <slot name="label">
                {{ label }}
                <span v-if="required" class="text-accent-600" aria-hidden="true">*</span>
                <span v-if="optional" class="font-normal text-slate-400">(optional)</span>
            </slot>
        </label>

        <div class="relative">
            <select
                :id="id"
                v-model="model"
                :name="name ?? undefined"
                :required="required"
                :aria-invalid="!!error || undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="block w-full appearance-none rounded-lg border bg-white px-4 py-2.5 pr-10 text-sm text-slate-900 transition-colors duration-200 focus:outline-none focus:ring-2 min-h-[2.75rem]"
                :class="error
                    ? 'border-accent-400 focus:border-accent-500 focus:ring-accent-200'
                    : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100'"
            >
                <!-- Selectable on an optional field, so a value can be taken
                     back out again. Disabled elsewhere, where the placeholder is
                     a prompt rather than a choice: on a required field picking
                     it would only produce a validation error. -->
                <option value="" :disabled="!optional">{{ placeholder }}</option>
                <template v-if="grouped">
                    <optgroup v-for="group in grouped" :key="group.label" :label="group.label">
                        <option v-for="opt in group.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </optgroup>
                </template>
                <template v-else>
                    <option v-for="opt in normalized" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </template>
            </select>
            <AppIcon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        </div>

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-accent-600" role="alert">
            {{ error }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-sm text-slate-500">
            {{ hint }}
        </p>
    </div>
</template>
