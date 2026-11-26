/*
 * Service worker: lets phones and computers install the app (PWA).
 *
 * Deliberately minimal, because the app shows personal data (salaries,
 * documents) and phones can be shared or lost:
 * - pages and data are NEVER cached, they always come from the server;
 * - only the built JS/CSS files (/build/assets, names change at every build)
 *   and the icons are cached, so the app opens faster;
 * - without a connection, a page opened from the app shows offline.html.
 *
 * Bump VERSION when this file or offline.html changes.
 */
const VERSION = 'v1';
const CACHE = `conge-${VERSION}`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('conge-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Full page loads: always the network; the offline page if there is none.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Built files and icons never change under the same name: cache first.
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
    // Everything else (Inertia data, documents, PDFs): untouched, straight to the server.
});
