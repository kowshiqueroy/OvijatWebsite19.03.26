// Name of the cache
const CACHE_NAME = "my-app-cache-v1";

// Files to cache
const URLS_TO_CACHE = [
  "index.php",
  "offline.php",
  "style.css",   // add your CSS if any
  "script.js"    // add your JS if any
];

// Install event: cache files
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(URLS_TO_CACHE);
    })
  );
});

// Activate event: cleanup old caches
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(name => {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    })
  );
});

// Fetch event: serve cached files or offline page
self.addEventListener("fetch", event => {
  event.respondWith(
    fetch(event.request).catch(() => {
      // If request fails (offline), show offline.php
      if (event.request.mode === "navigate") {
        return caches.match("offline.php");
      }
    })
  );
});