import { reactive } from 'vue';

const STORAGE_PREFIX = 'scanner:pendingQueue';
const MAX_ATTEMPTS = 10;

/**
 * Key for one scan destination. Queues must not be shared across destinations:
 * a replay posts to whichever page is currently open, so a scan queued on the
 * staff scanner and replayed from a public /scan/{token} page would record
 * attendance against the token's examination instead of the intended one.
 */
const storageKey = (scope) => `${STORAGE_PREFIX}:${scope}`;

const read = (key) => {
    if (typeof window === 'undefined') return [];
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
};

/**
 * Entries queued before scan queues were scoped lived under the bare
 * `scanner:pendingQueue` key, and only the staff scanner ever wrote it. Adopt
 * them once rather than stranding real unsent attendance on deploy.
 */
const adoptLegacyQueue = (scope) => {
    if (typeof window === 'undefined' || scope !== 'staff') return [];

    const legacy = read(STORAGE_PREFIX);
    window.localStorage.removeItem(STORAGE_PREFIX);

    return legacy;
};

/** One reactive queue per scope, so repeat calls in a page share state. */
const queues = new Map();

const queueFor = (scope) => {
    if (!queues.has(scope)) {
        const adopted = adoptLegacyQueue(scope);
        const entries = reactive([...adopted, ...read(storageKey(scope))]);

        // Write adopted entries through straight away: the legacy key is gone
        // now, and nothing else persists until the queue next changes.
        if (adopted.length && typeof window !== 'undefined') {
            window.localStorage.setItem(storageKey(scope), JSON.stringify(entries));
        }

        queues.set(scope, { entries, nextId: Math.max(0, ...entries.map((entry) => entry.id)) + 1 });
    }

    return queues.get(scope);
};

/**
 * Scans that failed to reach the server (flaky venue wifi) — held here instead
 * of being silently lost, persisted to localStorage so a page reload doesn't
 * drop them, and replayed against the same idempotent endpoint once
 * connectivity returns.
 *
 * `scope` identifies the destination the queued scans belong to: the session
 * token on a public scanner link, or 'staff' on the authenticated scanner.
 */
export function useScanQueue(scope = 'staff') {
    const state = queueFor(scope);
    const queue = state.entries;
    const key = storageKey(scope);

    const persist = () => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(key, JSON.stringify(queue));
    };

    const enqueue = (code, context) => {
        queue.push({ id: state.nextId++, code, context, attempts: 0, status: 'pending', queuedAt: Date.now() });
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
