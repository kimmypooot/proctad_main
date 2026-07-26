import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Reactive online/offline state, shared app-wide. The scanner tracks its own
 * connectivity for the scan queue; this is the lighter, general-purpose signal
 * behind the header's offline banner so every page — not just the scanner —
 * tells a field officer plainly when the venue's connection has dropped.
 *
 * `navigator.onLine` is optimistic (it only knows the interface is up, not that
 * the internet is reachable), which is exactly the right bias for a banner:
 * warn on a definite disconnect, don't cry wolf on a maybe.
 */
export function useOnline() {
    const online = ref(typeof navigator === 'undefined' || navigator.onLine !== false);

    const setOnline = () => { online.value = true; };
    const setOffline = () => { online.value = false; };

    onMounted(() => {
        window.addEventListener('online', setOnline);
        window.addEventListener('offline', setOffline);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('online', setOnline);
        window.removeEventListener('offline', setOffline);
    });

    return { online };
}
