/* Academic Hub service worker.
 *
 * Offline reading: the viewer renders each page as a watermarked image
 * served with a short-lived token, so the offline layer caches the
 * RENDERED page images (never the PDF) under stable keys that the
 * self-contained offline hub (public/offline.html) reads via the Cache
 * Storage API — no auth round-trips needed offline.
 *
 * Cache names are the contract shared with src/lib/offline.ts and
 * public/offline.html — keep all three in sync.
 */
const SHELL_CACHE = "arh-shell-v2";
const PAGES_CACHE = "arh-pages-v1";
const META_CACHE = "arh-meta-v1";
const OFFLINE_URL = "/offline.html";

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) => cache.add(OFFLINE_URL))
      .catch(() => undefined),
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter(
              (k) => k !== SHELL_CACHE && k !== PAGES_CACHE && k !== META_CACHE,
            )
            .map((k) => caches.delete(k)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Viewer page images: serve from the network while mirroring the
  // rendered image into the offline cache under a stable key. If the
  // network fails, fall back to the cached copy (stale tab case).
  const pageMatch = url.pathname.match(/^\/api\/viewer\/([^/]+)\/pages\/(\d+)$/);
  if (pageMatch) {
    const [, slug, page] = pageMatch;
    const cacheKey = new URL(`/__offline/${slug}/${page}`, self.location.origin);
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            caches
              .open(PAGES_CACHE)
              .then((cache) => cache.put(cacheKey, response.clone()))
              .catch(() => undefined);
          }
          return response;
        })
        .catch(() =>
          caches
            .open(PAGES_CACHE)
            .then((cache) => cache.match(cacheKey))
            .then(
              (cached) =>
                cached ??
                new Response("Page not available offline.", { status: 404 }),
            ),
        ),
    );
    return;
  }

  // Navigations: network-first, offline shell fallback.
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(() =>
        caches
          .open(SHELL_CACHE)
          .then((cache) => cache.match(OFFLINE_URL))
          .then(
            (res) =>
              res ??
              new Response("You are offline.", {
                status: 503,
                headers: { "Content-Type": "text/plain" },
              }),
          ),
      ),
    );
  }
});