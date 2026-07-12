import { reactive } from 'vue';

const AUTO_DISMISS_MS = 5000;

const toasts = reactive([]);
let nextId = 1;

const dismiss = (id) => {
    const index = toasts.findIndex((t) => t.id === id);
    if (index !== -1) toasts.splice(index, 1);
};

const push = (variant, message) => {
    if (!message) return;
    const id = nextId++;
    toasts.push({ id, variant, message });
    setTimeout(() => dismiss(id), AUTO_DISMISS_MS);
};

/** App-wide toast queue (singleton) — flash messages surface here instead of an inline page banner. */
export function useToasts() {
    return { toasts, push, dismiss };
}
