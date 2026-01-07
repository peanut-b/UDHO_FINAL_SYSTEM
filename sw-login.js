// sw-login.js
const CACHE_NAME = 'udho-login-v1.0.0';
const API_CACHE_NAME = 'udho-api-data';

// Files to cache for offline login
const urlsToCache = [
  '/',
  '/index.php',
  '/assets/BG_LOGIN.png',
  '/assets/bg_1.png',
  'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Install event
self.addEventListener('install', (event) => {
  console.log('🚀 Login Service Worker installing...');
  event.waitUntil(
    Promise.all([
      caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache)),
      caches.open(API_CACHE_NAME).then(cache => {
        // Cache initial user data if available
        return cache.put('/api/users', new Response(JSON.stringify([])));
      })
    ]).then(() => {
      console.log('✅ Login resources cached successfully');
      return self.skipWaiting();
    })
  );
});

// Activate event
self.addEventListener('activate', (event) => {
  console.log('🔄 Login Service Worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
            console.log('🗑️ Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  
  // Handle API requests for user data
  if (url.pathname === '/api/sync-users' && event.request.method === 'POST') {
    event.respondWith(handleUserSync(event.request));
    return;
  }
  
  // Handle login requests
  if (url.pathname === '/index.php' && event.request.method === 'POST') {
    event.respondWith(handleOfflineLogin(event.request));
    return;
  }
  
  // Serve cached pages for offline access
  if (event.request.method === 'GET') {
    event.respondWith(
      caches.match(event.request).then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request).then(response => {
          // Cache successful responses
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        }).catch(() => {
          // Return offline page for navigation requests
          if (event.request.destination === 'document') {
            return caches.match('/');
          }
          return new Response('Network error happened', { status: 408 });
        });
      })
    );
  }
});

// Handle user data sync
async function handleUserSync(request) {
  try {
    const userData = await request.json();
    
    // Store user data in cache
    const cache = await caches.open(API_CACHE_NAME);
    await cache.put('/api/users', new Response(JSON.stringify(userData)));
    
    return new Response(JSON.stringify({ success: true }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });
  } catch (error) {
    return new Response(JSON.stringify({ error: 'Sync failed' }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Handle offline login
async function handleOfflineLogin(request) {
  try {
    const formData = await request.formData();
    const username = formData.get('username');
    const password = formData.get('password');
    const role = formData.get('role');
    
    // Get cached user data
    const cache = await caches.open(API_CACHE_NAME);
    const response = await cache.match('/api/users');
    
    if (!response) {
      return new Response(JSON.stringify({ 
        success: false, 
        error: 'No cached user data available' 
      }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' }
      });
    }
    
    const users = await response.json();
    
    // Find matching user
    const user = users.find(u => 
      u.username === username && 
      u.role === role && 
      u.password === password
    );
    
    if (user) {
      // Successful offline login
      return new Response(JSON.stringify({
        success: true,
        user: {
          id: user.id,
          username: user.username,
          role: user.role,
          profile_picture: user.profile_picture
        },
        offline: true
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      });
    } else {
      // Failed login
      return new Response(JSON.stringify({
        success: false,
        error: 'Invalid credentials'
      }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  } catch (error) {
    return new Response(JSON.stringify({
      success: false,
      error: 'Login processing failed'
    }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}