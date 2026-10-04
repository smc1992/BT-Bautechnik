const CACHE_NAME = 'bt-bautechnik-static-v3';
const STATIC_ASSETS = ['/favicon.ico', '/manifest.json', '/images/branding/bt-monogram-v2.png'];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('bt-bautechnik-') && key !== CACHE_NAME).map(key => caches.delete(key)))));
    self.clients.claim();
});
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    // Livewire, forms and authenticated HTML always use the network. Drafts are explicit.
    if (event.request.method !== 'GET' || url.origin !== self.location.origin || (!url.pathname.startsWith('/build/') && !STATIC_ASSETS.includes(url.pathname))) return;
    const response = caches.open(CACHE_NAME).then(async cache => {
        const cached = await cache.match(event.request);
        if (cached) return cached;
        const fresh = await fetch(event.request);
        if (fresh.ok) await cache.put(event.request, fresh.clone());
        return fresh;
    });
    event.respondWith(response);
});
