import { reactive } from 'vue';

const STORAGE_KEY = 'scanner:pendingQueue';
const MAX_ATTEMPTS = 10;

const load = () => {
    if (typeof window === 'undefined') return [];
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
};

const queue = reactive(load());

const persist = () => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
};

let nextId = Math.max(0, ...queue.map((entry) => entry.id)) + 1;

/**
 * Scans that failed to reach the server (flaky venue wifi) — held here instead
 * of being silently lost, persisted to localStorage so a page reload doesn't
 * drop them, and replayed against the same idempotent `/scanner` endpoint
 * once connectivity returns.
 */
export function useScanQueue() {
    const enqueue = (code, context) => {
        queue.push({ id: nextId++, code, context, attempts: 0, status: 'pending', queuedAt: Date.now() });
        persist();
    };

    const remove = (id) => {
        const index = queue.findIndex((entry) => entry.id === id);
        if (index !== -1) queue.splice(index, 1);
        persist();
    };

    /**
     * Replays every pending/failed-but-retryable entry through `replayFn(code, context)`,
     * which must return a Promise that resolves on success, rejects on failure.
     */
    const retryAll = async (replayFn) => {
        for (const entry of [...queue]) {
            if (entry.status === 'failed') continue;

            entry.status = 'retrying';
            entry.attempts++;

            try {
                await replayFn(entry.code, entry.context);
                remove(entry.id);
            } catch {
                entry.status = entry.attempts >= MAX_ATTEMPTS ? 'failed' : 'pending';
                persist();
            }
        }
    };

    return { queue, enqueue, remove, retryAll };
}
