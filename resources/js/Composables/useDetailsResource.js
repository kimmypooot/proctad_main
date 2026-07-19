import { ref } from 'vue';
import { fetchJson, messageFor } from '@/Composables/useJsonFetch';

/**
 * The load-details-into-a-modal pattern, which was copy-pasted across the
 * member, OEP, scanner, service-history and training view modals: reset, fetch,
 * expose `loading` while in flight, null out on failure.
 *
 * `url` is a function rather than a string so it is read at call time — the
 * modals are kept mounted and swap which record they are showing via a prop.
 *
 * @param {() => string} url          Resolves the endpoint for the current record.
 * @param {string}       failureText  Shown for ordinary failures; session
 *                                    expiry always reports itself instead, so
 *                                    a signed-out user is not told the record
 *                                    could not be found.
 */
export function useDetailsResource(url, failureText) {
    const loading = ref(false);
    const data = ref(null);
    const error = ref(null);

    /** Resolves to the payload, or null on failure — callers that need to do
     *  extra work with the result can await it rather than watching `data`. */
    const load = async () => {
        loading.value = true;
        data.value = null;
        error.value = null;

        try {
            data.value = await fetchJson(url());

            return data.value;
        } catch (e) {
            data.value = null;
            error.value = messageFor(e, failureText);

            return null;
        } finally {
            loading.value = false;
        }
    };

    const reset = () => {
        data.value = null;
        error.value = null;
        loading.value = false;
    };

    return { loading, data, error, load, reset };
}
