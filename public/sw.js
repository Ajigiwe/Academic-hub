/**
 * Service Worker for Academic Hub PWA - offline library access
 * and install prompt handling
 *
 * Caches PDF documents, PDF.js viewer, and critical assets so students
 * can access their library files when offline after purchasing/paying.
 *
 * Strategy:
 * - Cache first for: PDF.js worker, assets, previously viewed resources
 * - Network first for: API responses (to always get fresh entitlement state)
 * - Stale-while-revalidate for: HTML pages
 * - beforeinstallprompt: Show install banner for A2HS
 */

const CACHE_NAME = 'academic-hub-v3';
const OFFLINE_URL = '/offline';

// Assets to cache immediately on install (only files that exist — a
// 404 here must not break the install)
const ASSETS_TO_CACHE = [
  '/',
  '/offline',
  '/manifest.json',
  '/icon.svg',
  '/js/pdf-reader.js',
];

// (Install-prompt capture lives in the page: beforeinstallprompt fires
// on window, never inside the service worker.)

// Install event - cache critical assets (tolerant: a single missing
// file must not abort the whole install — addAll rejects on any 404)
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Caching critical assets');
        return Promise.all(ASSETS_TO_CACHE.map((url) =>
          fetch(url, { cache: 'reload' })
            .then((resp) => (resp.ok ? cache.put(url, resp) : undefined))
            .catch(() => undefined)
        ));
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      );
    })
      .then(() => self.clients.claim())
  );
});

// Fetch event - implement offline strategy
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only handle same-origin requests for our app
  if (url.origin !== self.location.origin) {
    return;
  }

  // HTML pages: network first (so a freshly deployed page is never
  // masked by a stale cached copy), cache only as the offline fallback.
  if (url.pathname.endsWith('.html') || url.pathname === '/') {
    event.respondWith(
      networkFirst(event.request)
    );
    return;
  }

  // PDF documents and resources: cache first, then network
  if (url.pathname.endsWith('.pdf') || url.pathname.match(/\/resources\//)) {
    event.respondWith(
      cacheFirst(event.request)
    );
    return;
  }

  // PDF.js assets: cache first
  if (url.pathname.includes('pdfjs') || url.pathname.endsWith('.worker.js')) {
    event.respondWith(
      cacheFirst(event.request)
    );
    return;
  }

  // API requests: network first, fallback to offline
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/')) {
    event.respondWith(
      networkFirst(event.request)
        .catch(() => caches.match(event.request)
          .then((response) => response || fetch(OFFLINE_URL)))
    );
    return;
  }

  // Default: network first
  event.respondWith(networkFirst(event.request));
});

// NOTE: beforeinstallprompt is a WINDOW event — it fires in the page,
// never in the service worker. The layout's inline script captures it,
// dispatches 'sw:installprompt', and the install banner shows from there.

// Cache first: try cache, fall back to network
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }

  try {
    const response = await fetch(request);
    // Don't cache non-GET requests or error responses
    if (request.method === 'GET' && response && response.status === 200) {
      const responseClone = response.clone();
      caches.open(CACHE_NAME).then((cache) => {
        cache.put(request, responseClone);
      });
    }
    return response;
  } catch (error) {
    // If offline, fall back to the cached copy (or an error response)
    return (await caches.match(request)) || Response.error();
  }
}

// Network first: try network, fall back to cache
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (request.method === 'GET' && response && response.status === 200) {
      const responseClone = response.clone();
      caches.open(CACHE_NAME).then((cache) => {
        try { cache.put(request, responseClone); } catch (e) { /* quota etc */ }
      });
    }
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    // Return offline fallback
    return (await caches.match('/offline')) || Response.error();
  }
}