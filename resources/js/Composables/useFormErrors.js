import { watch } from 'vue';

/**
 * Server-side validation recovery.
 *
 * Inertia returns validation errors and re-renders the page at its current
 * scroll position. On a short form that's fine. On a long one — the member
 * record, the OEP record — the user is left at the top with the rejected field
 * somewhere below the fold, marked only by red text they have to hunt for. On a
 * phone, where a dozen fields are a dozen screens, they frequently just give up.
 *
 * These helpers move the user to the problem instead of making them look for it.
 */

/**
 * Resolve the control for a field key. Relies on the `name` prop being set on
 * TextInput/SelectInput/TextArea/CheckboxInput/FileInput/RatingGrid — without it
 * the field is not addressable and we quietly do nothing rather than guess.
 *
 * Laravel's error bag uses dotted keys for nested and indexed fields
 * (`ratings.0`, `rooms.0.name`), while the DOM `name` may be the raw key, the
 * bracketed form, or — for RatingGrid's radio groups — the dashed form. Try each
 * in turn, then fall back to a prefix match so a whole-collection error like
 * `ratings` still lands on the collection's first control.
 */
const findField = (key, root = document) => {
    const candidates = [
        key,
        key.replace(/\.(\w+)/g, '[$1]'),
        key.replace(/\./g, '-'),
    ];

    for (const candidate of candidates) {
        const match = root.querySelector(`[name="${CSS.escape(candidate)}"]`);
        if (match) return match;
    }

    return root.querySelector(`[name^="${CSS.escape(key)}-"]`);
};

/**
 * Scroll the first rejected field into view and focus it. Centred rather than
 * scrolled to the top edge, so the field's label and error text are both visible
 * rather than tucked under a sticky header.
 */
export const focusFirstError = (errors, root = document) => {
    const firstKey = Object.keys(errors ?? {})[0];
    if (!firstKey) return false;

    const field = findField(firstKey, root);
    if (!field) return false;

    field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    // Focus after the scroll starts; focusing first makes the browser jump to
    // the field instantly and the smooth scroll then has nowhere to travel.
    setTimeout(() => field.focus({ preventScroll: true }), 120);
    return true;
};

/**
 * Wire an Inertia `useForm()` object up so any new batch of server errors pulls
 * the user to the first one. Call from `setup()`.
 */
export const useFormErrors = (form) => {
    watch(
        () => form.errors,
        (errors) => {
            if (Object.keys(errors ?? {}).length === 0) return;
            // Wait for the error markup to render before measuring positions.
            requestAnimationFrame(() => focusFirstError(errors));
        },
        { deep: true },
    );

    return { focusFirstError };
};
