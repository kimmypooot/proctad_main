/**
 * ProCTAD service worker — exam-day offline resilience.
 *
 * The scanner already queues failed scans in localStorage (see useScanQueue),
 * but that only helps once the page is already open. This closes the other half:
 * it lets the app shell BOOT with no connection, so a proctor whose phone dropped
 * the tab — or who arrives at a dead-wifi venue cold — can still open the scanner.
 *
 * Deliberately hand-written (no Workbox/build plugin) to keep the dependency
 * surface as lean as the rest of the project. Strategy:
 *   - Hashed build assets, fonts, brand imagery ....... cache-first (immutable)
 *   - Scanner navigations (/scanner, /scan/...) ........ network-only, with a
 *     prop-free offline shell served when the network is gone
 *   - Any other navigation offline ..................... /offline.html fallback
 *
 * What is deliberately NOT cached: the HTML of an authenticated scanner page.
 * It embeds its Inertia props, which after a scan contain the member's name,
 * PROCTAD ID, agency, membership status and — during an examination — their
 * whole service history. Caching that put one operator's last scan on a shared
 * venue phone, readable by the next person to open it offline, with no session
 * required and nothing clearing it at logout.
 *
 * Bump VERSION on any change here to retire old caches on activate.
 */
const VERSION = 'proctad-v2';
const SHELL_CACHE = VERSION + '-shell';
const ASSET_CACHE = VERSION + '-assets';

/** Precached so the offline experience works on the very first failure. */
const SHELL_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/images/brand/proctad-logo.png',
];


self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    /** Let the page trigger an immediate activation after an update. */
    if (event.data === 'skip-waiting') self.skipWaiting();

    /**
     * Sent on logout. Nothing sensitive is cached any more, but a phone that
     * was running the previous service worker still holds its DOC_CACHE, and
     * the operator signing out is the moment to be rid of it.
     */
    if (event.data === 'purge-caches') {
        event.waitUntil(
            caches.keys().then((keys) => Promise.all(
                keys.filter((key) => key.endsWith('-docs')).map((key) => caches.delete(key)),
            )),
        );
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Immutable, content-addressed assets — safe to serve from cache forever.
    if (
        url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/images/')
        || /\.(?:woff2?|ttf|otf|css|js)$/.test(url.pathname)
    ) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request, url));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch (error) {
        // No cached copy and no network — let the failure surface.
        return cached || Response.error();
    }
}

/**
 * Network-only, with a static fallback. The response is never written to a
 * cache: on the scanner surfaces it carries whoever was last scanned, and this
 * runs on shared venue phones.
 *
 * The app still boots offline — that was the actual requirement — because the
 * shell, the hashed JS/CSS bundles and the offline notice are all cached
 * above. What no longer survives is anybody's personal data.
 */
async function handleNavigation(request, url) {
    try {
        return await fetch(request);
    } catch (error) {
        const shell = await caches.open(SHELL_CACHE);

        return (await shell.match('/offline.html')) || Response.error();
    }
}
