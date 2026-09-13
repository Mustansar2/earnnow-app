/**
 * EarnNow - Progressive Web App Service Worker
 * 
 * Philosophy: Safe, secure, and dynamic-first for PHP + MySQL.
 * - Dynamic PHP pages: Always Network-First. Never stores sensitive account data in cache.
 * - Static Assets (CSS, JS, Icons): Cache-First / Stale-While-Revalidate for instant loading.
 * - Offline Fallback: Serves offline.html when network is unreachable.
 * - Strict Blacklist: Auth, passwords, logout, and POST requests are strictly network-only.
 */

const CACHE_VERSION = 'earnnow-v1.0.0';
const STATIC_CACHE = `earnnow-static-${CACHE_VERSION}`;
const OFFLINE_CACHE = `earnnow-offline-${CACHE_VERSION}`;

// Pre-cache only immutable, non-sensitive static assets and the offline fallback
const PRECACHE_ASSETS = [
  './offline.html',
  './manifest.json',
  './assets/css/style.css',
  './assets/js/pwa-install.js',
  './assets/icons/icon-192x192.png',
  './assets/icons/icon-512x512.png',
  './assets/icons/icon-maskable-512x512.png',
  './assets/icons/apple-touch-icon.png',
  './assets/icons/favicon.png'
];

// Sensitive endpoints & patterns that must NEVER be cached
const SENSITIVE_PATTERNS = [
  /\/login(\.php)?/i,
  /\/logout(\.php)?/i,
  /\/register(\.php)?/i,
  /\/password/i,
  /\/change-password/i,
  /\/reset-password/i,
  /\/admin/i,
  /\/api\//i,
  /\/ajax\//i,
  /\.php$/i // Any PHP file execution
];

// 1. Install Event: Cache essential static shell assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      // Use cache.addAll with fault tolerance
      return Promise.allSettled(
        PRECACHE_ASSETS.map((url) =>
          fetch(url, { cache: 'reload' })
            .then((res) => {
              if (res.ok) {
                return cache.put(url, res);
              }
              return Promise.resolve();
            })
            .catch(() => Promise.resolve())
        )
      );
    }).then(() => {
      // Activate immediately without waiting for page reload
      return self.skipWaiting();
    })
  );
});

// 2. Activate Event: Clean up legacy caches from previous versions
self.addEventListener('activate', (event) => {
  const currentCaches = [STATIC_CACHE, OFFLINE_CACHE];
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (!currentCaches.includes(cacheName)) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      // Take control of all clients immediately
      return self.clients.claim();
    })
  );
});

// Helper: Check if a URL matches sensitive or dynamic PHP endpoints
function isSensitiveOrDynamic(url) {
  const pathname = url.pathname;
  return SENSITIVE_PATTERNS.some((pattern) => pattern.test(pathname));
}

// 3. Fetch Event: Intelligent routing
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // A. Only intercept GET requests. POST, PUT, DELETE go directly to server.
  if (request.method !== 'GET') {
    return;
  }

  // B. Cross-origin requests (external analytics, CDNs) pass through
  if (url.origin !== self.location.origin) {
    return;
  }

  // C. HTML Navigation Requests (e.g. user visits profile.php, index.php, etc.)
  // Strategy: STRICT NETWORK-FIRST. Never cache dynamic PHP/MySQL output.
  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(
      fetch(request)
        .then((networkResponse) => {
          // Return live dynamic response directly to the user.
          // We intentionally DO NOT cache dynamic PHP responses to protect user privacy & sessions!
          return networkResponse;
        })
        .catch(() => {
          // If network fails (offline), serve the cached branded offline fallback page
          return caches.match('./offline.html').then((offlineResponse) => {
            return offlineResponse || new Response('Offline - No connection available.', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: { 'Content-Type': 'text/plain; charset=utf-8' }
            });
          });
        })
    );
    return;
  }

  // D. Sensitive or PHP script requests that are not navigations
  if (isSensitiveOrDynamic(url)) {
    // Network only; do not intercept or cache
    return;
  }

  // E. Static Assets (CSS, JS, Images, Fonts, Icons)
  // Strategy: Stale-While-Revalidate (Fast load + background refresh)
  const isStaticAsset = (
    request.destination === 'style' ||
    request.destination === 'script' ||
    request.destination === 'image' ||
    request.destination === 'font' ||
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('.json') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js')
  );

  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        const fetchPromise = fetch(request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
              const responseToCache = networkResponse.clone();
              caches.open(STATIC_CACHE).then((cache) => {
                cache.put(request, responseToCache);
              });
            }
            return networkResponse;
          })
          .catch(() => cachedResponse);

        return cachedResponse || fetchPromise;
      })
    );
    return;
  }

  // Default: let standard browser networking handle anything else
});

// 4. Message Event: Allow skip waiting triggers if requested
self.addEventListener('message', (event) => {
  if (event.data && event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});
