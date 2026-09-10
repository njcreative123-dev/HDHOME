/**
 * HDHome Live TV - Service Worker
 * Network-first for the app shell, cache-first for static assets.
 */
const CACHE = 'hdhome-v1';
const STATIC_ASSETS = ['/assets/css/style.css', '/assets/css/admin.css'];

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE).then((c) => c.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (url.origin !== location.origin || e.request.method !== 'GET') return;

    // API requests: network only (no stale cache)
    if (url.pathname.startsWith('/api') || url.pathname.includes('api.php')) {
        e.respondWith(fetch(e.request));
        return;
    }

    // Static assets: cache-first
    if (url.pathname.startsWith('/assets/')) {
        e.respondWith(
            caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
                const clone = res.clone();
                caches.open(CACHE).then((c) => c.put(e.request, clone));
                return res;
            }))
        );
        return;
    }

    // Pages: network-first with cache fallback
    e.respondWith(
        fetch(e.request).then((res) => {
            const clone = res.clone();
            caches.open(CACHE).then((c) => c.put(e.request, clone));
            return res;
        }).catch(() => caches.match(e.request))
    );
});
