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
 *   - Scanner navigations (/scanner, /scan/...) ........ network-first, cached
 *     copy served offline so the shell reboots
 *   - Any other navigation offline ..................... /offline.html fallback
 *
 * Bump VERSION on any change here to retire old caches on activate.
 */
const VERSION = 'proctad-v1';
const SHELL_CACHE = VERSION + '-shell';
const ASSET_CACHE = VERSION + '-assets';
const DOC_CACHE = VERSION + '-docs';

/** Precached so the offline experience works on the very first failure. */
const SHELL_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/images/brand/proctad-logo.png',
];

/**
 * Only these navigations have their HTML cached for an offline reboot. Scoped
 * to the exam-day surfaces on purpose — we don't want to warehouse every
 * authenticated admin page (each embeds its own props) in the cache.
 */
const OFFLINE_DOC_PATHS = [/^\/scanner(\/|$)/, /^\/scan\//];

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

/** Let the page trigger an immediate activation after an update. */
self.addEventListener('message', (event) => {
    if (event.data === 'skip-waiting') self.skipWaiting();
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

async function handleNavigation(request, url) {
    const cacheable = OFFLINE_DOC_PATHS.some((pattern) => pattern.test(url.pathname));

    try {
        const response = await fetch(request);
        if (cacheable && response.ok) {
            const cache = await caches.open(DOC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        if (cacheable) {
            const cache = await caches.open(DOC_CACHE);
            const cached = await cache.match(request, { ignoreSearch: true });
            if (cached) return cached;
        }

        const shell = await caches.open(SHELL_CACHE);
        return (await shell.match('/offline.html')) || Response.error();
    }
}
