// sw.js
const CACHE_NAME = 'housing-survey-v1.3.0';
const urlsToCache = [
  '/',
  '/survey-form.php',
  '/index.php',
  'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
  'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
  'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js',
  'https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js',
  'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js'
];

// Install event - cache essential files
self.addEventListener('install', (event) => {
  console.log('🚀 Service Worker installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('📦 Opened cache');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('✅ All resources cached successfully');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('❌ Failed to cache resources:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('🔄 Service Worker activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('🗑️ Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('✅ Service Worker activated');
      return self.clients.claim();
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests and PHP POST requests
  if (event.request.method !== 'GET' || 
      event.request.url.includes('/survey-form.php') && event.request.method === 'POST') {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Return cached version or fetch from network
        if (response) {
          console.log('💾 Serving from cache:', event.request.url);
          return response;
        }

        return fetch(event.request)
          .then((response) => {
            // Check if we received a valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone the response
            const responseToCache = response.clone();

            // Add to cache for future requests
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch((error) => {
            console.log('❌ Fetch failed; returning offline page:', error);
            // If both cache and network fail, return offline page
            return new Response(`
              <!DOCTYPE html>
              <html>
              <head>
                <title>Offline - Housing Survey</title>
                <style>
                  body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                  .offline-message { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; }
                </style>
              </head>
              <body>
                <div class="offline-message">
                  <h1>📴 You are offline</h1>
                  <p>Please check your internet connection and try again.</p>
                  <p>Your form data is saved locally and will sync when you're back online.</p>
                  <button onclick="window.location.reload()">Retry</button>
                </div>
              </body>
              </html>
            `, {
              status: 200,
              headers: { 'Content-Type': 'text/html' }
            });
          });
      })
  );
});

// Background sync for offline data
self.addEventListener('sync', (event) => {
  if (event.tag === 'background-sync') {
    console.log('🔄 Background sync triggered');
    event.waitUntil(doBackgroundSync());
  }
});

async function doBackgroundSync() {
  // This would sync offline data when connection is restored
  console.log('🔄 Performing background sync...');
  // You can implement background sync logic here
}