// ============================================
// Dadai - Service Worker
// Cache-first for local assets, network-first
// for CDN (with cache fallback for offline use)
// ============================================

const CACHE_NAME = 'dadai-static-v2';
const STATIC_ASSETS = [
  '/dadai/',
  '/dadai/index.html',
  '/dadai/styles.css',
  '/dadai/app.js',
  '/dadai/manifest.json'
];
const LOCAL_ORIGINS = [self.location.hostname, 'localhost', '127.0.0.1'];

// Install: precache all static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate: clean old caches, take control
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => clients.claim())
  );
});

// Fetch: cache-first for local, network-first for CDN
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET and browser extension requests
  if (event.request.method !== 'GET' || url.protocol === 'chrome-extension:') return;

  // WebLLM CDN: network-first with cache fallback (for offline use after first load)
  if (url.hostname.includes('esm.run') || url.hostname.includes('jsdelivr') || url.hostname.includes('unpkg')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, clone);
          });
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Local assets: network-first with cache fallback (avoids stale-cache issues during dev)
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
