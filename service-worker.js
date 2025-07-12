self.addEventListener('install', function(event) {
  console.log('Service Worker installing.');
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  console.log('Service Worker activating.');
});

// Removed no-op fetch event handler to avoid overhead during navigation
// Add fetch event handler here only if custom fetch handling is needed in the future
