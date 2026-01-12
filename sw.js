const CACHE_NAME = 'school-ms-v2.0';

// Updated asset list to match project
const ESSENTIAL_ASSETS = [
    '/gebsco/',
    '/gebsco/index.php',
    '/gebsco/offline.html',
    '/gebsco/css/dashboard.css',
    '/gebsco/js/dashboard.js',
    '/gebsco/img/logo.png'
];

// In your sw.js file, update the install event:
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching assets:', ESSENTIAL_ASSETS);
                // Use Promise.all with individual cache.add() calls for better error handling
                // In your sw.js, update the caching strategy:
            return Promise.all(
                ESSENTIAL_ASSETS.map(asset => {
                    return cache.add(asset).catch(error => {
                        console.warn(`Failed to cache ${asset}:`, error);
                        // Skip this asset but continue with others
                        return Promise.resolve(); // Return resolved promise to continue
                    });
                })
            );
            })
            .catch(error => {
                console.error('Cache opening failed:', error);
            })
    );
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('Claiming clients');
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const request = event.request;

    // Skip non-HTTP/HTTPS or cross-origin requests
    if (!url.protocol.startsWith('http') || !isSameOrigin(url)) {
        return;
    }

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Bypass cache for sensitive paths or authenticated requests
    if (shouldBypassCache(url, request)) {
        event.respondWith(fetch(request).catch(() => {
            if (request.mode === 'navigate') {
                return caches.match('/gebsco/offline.html');
            }
            return new Response('Network error', { status: 503, statusText: 'Offline' });
        }));
        return;
    }

    event.respondWith(
        (async () => {
            try {
                // Try cache first
                const cachedResponse = await caches.match(request);
                if (cachedResponse) {
                    console.log('Serving from cache:', url.pathname);
                    return cachedResponse;
                }

                // Try network
                const networkResponse = await fetch(request);
                if (networkResponse.redirected) {
                    console.log('Redirect detected, not caching:', url.pathname);
                    return networkResponse;
                }

                // Cache successful responses
                if (networkResponse.ok && shouldCache(url)) {
                    const cache = await caches.open(CACHE_NAME);
                    cache.put(request, networkResponse.clone());
                    console.log('Cached new resource:', url.pathname);
                }

                return networkResponse;
            } catch (error) {
                console.error('Fetch failed:', error);
                const cachedResponse = await caches.match(request);
                if (cachedResponse) {
                    return cachedResponse;
                }

                if (request.mode === 'navigate') {
                    return caches.match('/gebsco/offline.html');
                }

                return new Response('Network error', { 
                    status: 503, 
                    statusText: 'Offline' 
                });
            }
        })()
    );
});

function shouldBypassCache(url, request) {
    const bypassPaths = [
        '/gebsco/login.php',
        '/gebsco/logout.php',
        '/gebsco/dashboard.php',
        '/gebsco/api/',
        '/gebsco/admin/'
    ];
    return (
        bypassPaths.some(path => url.pathname.includes(path)) ||
        request.headers.has('Authorization') ||
        request.headers.get('Cookie')?.includes('PHPSESSID') ||
        url.search.includes('nocache=true')
    );
}

function shouldCache(url) {
    const noCachePaths = [
        '/gebsco/login.php',
        '/gebsco/logout.php',
        '/gebsco/api/',
        '/gebsco/admin/'
    ];
    return !noCachePaths.some(path => url.pathname.includes(path)) && url.search === '';
}

function isSameOrigin(url) {
    return url.origin === self.location.origin;
}




// Add error handling for cache operations
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open('app-cache')
      .then((cache) => {
        return cache.addAll([
          '/',
          '/css/dashboard.css',
          '/css/attendance.css',
          // ... other resources
        ]).catch((error) => {
          console.log('Cache addAll error:', error);
          // Continue even if some resources fail to cache
        });
      })
  );
});