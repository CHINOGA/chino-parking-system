const CACHE_NAME = 'chino-parking-cache-v1';
const urlsToCache = [
  '/',
  '/vehicle_entry.php',
  '/vehicle_exit.php',
  '/reporting.php',
  '/revenue_report.php',
  '/navbar.php',
  '/manifest.json',
  '/css/styles.css', // Assuming you have a CSS file, adjust path if needed
  '/js/scripts.js'   // Assuming you have a JS file, adjust path if needed
];

// Install service worker and cache files
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Activate service worker and clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      );
    })
  );
});

// Fetch handler to serve cached content when offline
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});
