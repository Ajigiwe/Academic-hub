/**
 * Client-side offline reading helpers.
 *
 * Page images are watermarked server renders fetched with short-lived
 * tokens, so the offline layer caches the RENDERED PNGs (never the PDF)
 * under stable keys, plus a small metadata record per paper. The cache
 * names below are the contract shared with:
 *   - public/sw.js        (mirrors page images on every successful fetch)
 *   - public/offline.html (offline hub + reader reads the same caches)
 * Keep all three in sync.
 */
export const OFFLINE_PAGES_CACHE = "arh-pages-v1";
export const OFFLINE_META_CACHE = "arh-meta-v1";

export interface OfflinePaperMeta {
  slug: string;
  title: string;
  pageCount: number;
  savedAt: string; // ISO date
}

/** Cache Storage is only exposed on secure contexts (https / localhost). */
export function offlineSupported(): boolean {
  return typeof caches !== "undefined" && typeof caches.open === "function";
}

function pageKey(slug: string, page: number): string {
  return `/__offline/${slug}/${page}`;
}

function metaKey(slug: string): string {
  return `/__offline/meta/${slug}`;
}

export async function pageCachedOffline(slug: string, page: number): Promise<boolean> {
  if (!offlineSupported()) return false;
  try {
    const cache = await caches.open(OFFLINE_PAGES_CACHE);
    return (await cache.match(pageKey(slug, page))) !== undefined;
  } catch {
    return false;
  }
}

/** Stores one page image under its stable offline key (idempotent). */
export async function cachePageImage(
  slug: string,
  page: number,
  response: Response,
): Promise<void> {
  if (!response.ok) return;
  const cache = await caches.open(OFFLINE_PAGES_CACHE);
  await cache.put(pageKey(slug, page), response.clone());
}

export async function isSavedOffline(slug: string): Promise<boolean> {
  if (!offlineSupported()) return false;
  try {
    const cache = await caches.open(OFFLINE_META_CACHE);
    return (await cache.match(metaKey(slug))) !== undefined;
  } catch {
    return false;
  }
}

export async function writeOfflineMeta(meta: OfflinePaperMeta): Promise<void> {
  const cache = await caches.open(OFFLINE_META_CACHE);
  await cache.put(
    metaKey(meta.slug),
    new Response(JSON.stringify(meta), {
      headers: { "Content-Type": "application/json" },
    }),
  );
}