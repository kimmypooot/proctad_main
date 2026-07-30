<script setup>
import { computed, ref, useId } from 'vue';
import AppIcon from './AppIcon.vue';
import Tooltip from './Tooltip.vue';

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, required: true },
    /**
     * The form field key (e.g. `first_name`). The `id` is a generated `useId()`
     * and so can't be targeted from outside, which is what FormErrorSummary and
     * `focusFirstError()` need to move focus to a field the server rejected.
     * Also lets the browser's autofill recognise the field.
     */
    name: { type: String, default: null },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    placeholder: { type: String, default: null },
    inputmode: { type: String, default: null },
    min: { type: String, default: null },
    max: { type: String, default: null },
    /** Character limit. Declared as a prop because fallthrough attributes land
     *  on the outer wrapper, not the `<input>` — see `inputClass` below. */
    maxlength: { type: [String, Number], default: null },
    /** AppIcon name shown inside the field, on the left. Added because search
     *  boxes wanting one were hand-rolled instead — three copies of raw markup
     *  that each lost the label/input association this component provides. */
    icon: { type: String, default: null },
    /** Extra classes for the `<input>` itself — plain `class` on this component lands on the outer wrapper instead. */
    inputClass: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    /** Tooltip text shown beside the label via an info icon. */
    tooltip: { type: String, default: null },
    /**
     * Uppercase what is typed, for fields the server stores uppercase anyway
     * (see Member/OtherExaminationPersonnel's mutators). It transforms the
     * bound value rather than applying `text-transform`, so what the field
     * shows is what will be saved — a CSS-only version leaves the form
     * disagreeing with the record the moment it is submitted.
     */
    uppercase: { type: Boolean, default: false },
});

const id = useId();
const showPassword = ref(false);

// A computed proxy rather than an @input handler, so v-model keeps its own
// IME/composition handling instead of us fighting it with a second listener.
//
// The getter transforms too, not just the setter: a prefilled value (a staff
// account's name and agency, say) arrives in whatever case it is stored in, and
// the server will uppercase it on write regardless — so showing it as typed
// would reintroduce exactly the mismatch this prop exists to remove.
const cased = (input) => (props.uppercase && typeof input === 'string' ? input.toUpperCase() : input);
const value = computed({
    get: () => cased(model.value),
    set: (next) => {
        model.value = cased(next);
    },
});

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
        <label :for="id" class="mb-1.5 inline-flex items-center gap-1 text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-accent-600" aria-hidden="true">*</span>
            <span v-if="optional" class="font-normal text-slate-400">(optional)</span>
            <Tooltip v-if="tooltip" :text="tooltip" position="top" wrap class="-ml-0.5">
                <AppIcon name="information-circle" class="h-4 w-4 text-slate-400" />
            </Tooltip>
        </label>

        <div class="relative">
            <AppIcon
                v-if="icon"
                :name="icon"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />

            <input
                :id="id"
                v-model="value"
                :name="name ?? undefined"
                :type="inputType"
                :required="required"
                :disabled="disabled"
                :autocomplete="autocomplete ?? undefined"
                :placeholder="placeholder ?? undefined"
                :inputmode="inputmode ?? undefined"
                :min="min ?? undefined"
                :max="max ?? undefined"
                :maxlength="maxlength ?? undefined"
                :aria-invalid="!!error || undefined"
                :aria-describedby="describedBy"
                class="block w-full rounded-lg border bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 min-h-[2.75rem] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
                :class="[
                    error
                        ? 'border-accent-400 focus:border-accent-500 focus:ring-accent-200'
                        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-100',
                    isPassword ? 'pr-12' : '',
                    icon ? 'pl-10' : '',
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
