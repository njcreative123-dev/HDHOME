/**
 * HDHome Service Worker v2.1
 * Enhanced PWA with smart caching
 */
const CACHE_NAME = 'hdhome-v2.1';
const STATIC_ASSETS = [
    '/',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/js/player.js',
    '/assets/js/api.js',
    '/assets/js/favorites.js',
    '/assets/js/theme.js',
    '/assets/img/favicon.svg',
    '/assets/manifest.webmanifest'
];

/* ---- Install ---- */
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
    self.skipWaiting();
});

/* ---- Activate ---- */
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

/* ---- Fetch Strategy ---- */
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // API requests: Network-first with cache fallback
    if (url.pathname.startsWith('/api.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(request);
            })
        );
        return;
    }
    
    // Static assets: Cache-first
    if (request.destination === 'style' || request.destination === 'script' || request.destination === 'image') {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }
    
    // Pages: Stale-while-revalidate
    event.respondWith(
        caches.match(request).then(cached => {
            const fetchPromise = fetch(request).then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                }
                return response;
            }).catch(() => cached);
            
            return cached || fetchPromise;
        })
    );
});

/* ---- Background Sync ---- */
self.addEventListener('sync', event => {
    if (event.tag === 'sync-favorites') {
        event.waitUntil(syncFavorites());
    }
});

async function syncFavorites() {
    // Sync localStorage favorites to server when online
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({ type: 'SYNC_FAVORITES' });
    });
}

/* ---- Push Notifications ---- */
self.addEventListener('push', event => {
    const data = event.data?.json() || { title: 'HDHome', body: 'New content available!' };
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/assets/img/favicon.svg',
            badge: '/assets/img/favicon.svg',
            vibrate: [200, 100, 200],
            data: { url: data.url || '/' }
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data?.url || '/')
    );
});
