/**
 * Shared JSON fetching for the handful of endpoints called with fetch() rather
 * than through Inertia — the member/OEP/training detail and edit modals, the
 * member search boxes, and the report precheck.
 *
 * Every one of those call sites hand-rolled `if (!response.ok) throw` and then
 * swallowed everything into one generic message. That check misses the failure
 * that actually happens most: bootstrap/app.php limits JSON error rendering to
 * `api/*` (correct for Inertia), so an expired session redirects to /login and
 * a failed validation redirects back. fetch() follows both transparently, so
 * the caller sees status 200, `response.ok === true`, and an HTML body — then
 * `.json()` throws a parse error indistinguishable from a real outage, and the
 * user is told "could not load" when the truth is "you have been signed out".
 *
 * `response.redirected` is the signal that separates those cases.
 */

/** The session timed out; the request was bounced to the login page. */
export class SessionExpiredError extends Error {
    constructor() {
        super('Your session has expired. Please sign in again.');
        this.name = 'SessionExpiredError';
    }
}

/** Any other non-JSON or non-2xx outcome, carrying the status when we have one. */
export class RequestFailedError extends Error {
    constructor(message, status = null) {
        super(message);
        this.name = 'RequestFailedError';
        this.status = status;
    }
}

const isJson = (response) => response.headers.get('Content-Type')?.includes('application/json') ?? false;

/**
 * GET (or POST) JSON, throwing a typed error instead of returning something
 * that only looks like success. Resolves to the parsed body.
 */
export async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', ...(options.headers ?? {}) },
    });

    // We asked for JSON and got sent somewhere else instead — never the payload.
    if (response.redirected) {
        throw new URL(response.url).pathname.startsWith('/login')
            ? new SessionExpiredError()
            : new RequestFailedError('The server redirected instead of returning data.', response.status);
    }

    if (!response.ok) {
        // Error bodies are JSON when the endpoint opts into it (see
        // MemberController::downloadIdCardBulk) — prefer the server's wording.
        const message = isJson(response) ? (await response.json().catch(() => null))?.message : null;

        throw new RequestFailedError(message || 'The request failed.', response.status);
    }

    if (!isJson(response)) {
        throw new RequestFailedError('The server returned an unexpected response.', response.status);
    }

    return response.json();
}

/**
 * Turns a thrown error into something worth showing a user: the session-expired
 * wording when that is what happened, otherwise the caller's own fallback.
 */
export function messageFor(error, fallback) {
    return error instanceof SessionExpiredError ? error.message : fallback;
}
