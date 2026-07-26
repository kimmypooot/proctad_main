import { reactive } from 'vue';

/**
 * Floor for auto-dismissal. Longer messages get proportionally longer — five
 * seconds is not enough to read a two-line validation summary, and a toast that
 * vanishes mid-sentence may as well not have fired.
 */
const MIN_DURATION_MS = 5000;
const MS_PER_CHARACTER = 60;

/**
 * Errors never auto-dismiss. A failed save is the one message the user must
 * actually read — if it times out they are left looking at a form that simply
 * did nothing.
 */
const PERSISTENT_VARIANTS = ['error'];

/** Beyond this the stack covers the page it is reporting on. Oldest is evicted. */
const MAX_VISIBLE = 3;

const toasts = reactive([]);
const timers = new Map();
let nextId = 1;

const clearTimer = (id) => {
    const timer = timers.get(id);
    if (timer !== undefined) {
        clearTimeout(timer);
        timers.delete(id);
    }
};

const dismiss = (id) => {
    clearTimer(id);
    const index = toasts.findIndex((t) => t.id === id);
    if (index !== -1) toasts.splice(index, 1);
};

/**
 * (Re)start a toast's dismissal countdown. Called on push and again whenever the
 * pointer or focus leaves it, so hovering to read — or tabbing to the dismiss
 * button — holds it open (WCAG 2.2.1).
 */
const resume = (id) => {
    const toast = toasts.find((t) => t.id === id);
    if (!toast || toast.duration === null) return;
    clearTimer(id);
    timers.set(id, setTimeout(() => dismiss(id), toast.duration));
};

/**
 * `duration` overrides the variant default: milliseconds to auto-dismiss after,
 * or `null` to require an explicit dismissal. Needed by the scanner, where every
 * scan pushes a toast and a persistent error would hang over the next two
 * operators' scans.
 */
const push = (variant, message, { duration } = {}) => {
    if (!message) return;

    const id = nextId++;
    toasts.push({
        id,
        variant,
        message,
        duration: duration !== undefined
            ? duration
            : (PERSISTENT_VARIANTS.includes(variant)
                ? null
                : Math.max(MIN_DURATION_MS, message.length * MS_PER_CHARACTER)),
    });

    while (toasts.length > MAX_VISIBLE) dismiss(toasts[0].id);

    resume(id);
};

/** App-wide toast queue (singleton) — flash messages surface here instead of an inline page banner. */
export function useToasts() {
    return { toasts, push, dismiss, pause: clearTimer, resume };
}
