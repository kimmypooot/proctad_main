<script setup>
import { computed } from 'vue';
import BaseAlert from './BaseAlert.vue';
import { focusFirstError } from '@/Composables/useFormErrors';

/**
 * The "what went wrong" block for a rejected form submission: one place, at the
 * top, listing every failed field as a link that jumps to it.
 *
 * Pair it with `useFormErrors(form)` — that moves the user to the first error
 * automatically; this gives them the full list and a way back to each one. On a
 * long record form a server rejection can name five fields spread over several
 * screens, and hunting for red text is not a reasonable ask.
 *
 * `labels` maps field keys to the wording used on the field itself, so the
 * summary and the label agree. Keys without an entry fall back to a humanised
 * form of the key.
 */
const props = defineProps({
    /** An Inertia `useForm()` object, or any plain `{ field: message }` bag. */
    errors: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
    title: { type: String, default: null },
});

const entries = computed(() =>
    Object.entries(props.errors ?? {}).map(([key, message]) => ({
        key,
        message,
        label: props.labels[key] ?? key.replace(/[._]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
    })),
);

const heading = computed(() =>
    props.title
    ?? (entries.value.length === 1
        ? 'There is a problem with one field'
        : `There are problems with ${entries.value.length} fields`));

/**
 * This deliberately does not grab focus itself. BaseAlert carries `role="alert"`,
 * so the summary is announced the moment it renders, and `useFormErrors(form)`
 * is what moves focus — to the offending field, which announces its own label
 * and error via `aria-describedby`. Two things competing for focus on the same
 * error batch just means whichever fires last wins.
 */
</script>

<template>
    <BaseAlert v-if="entries.length" variant="error" :title="heading">
        <ul class="mt-1 space-y-1">
            <li v-for="entry in entries" :key="entry.key">
                <button
                    type="button"
                    class="text-left font-medium underline underline-offset-2 hover:no-underline"
                    @click="focusFirstError({ [entry.key]: entry.message })"
                >
                    {{ entry.label }}
                </button>
                <span class="text-accent-700"> — {{ entry.message }}</span>
            </li>
        </ul>
    </BaseAlert>
</template>
