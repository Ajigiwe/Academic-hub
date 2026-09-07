"use client";

import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";

interface DocumentViewerProps {
  slug: string;
  title: string;
  pageCount: number;
}

type ViewerError = {
  kind: "denied" | "session" | "missing" | "network" | "render";
  message: string;
} | null;

type ReadMode = "single" | "continuous";

const MODE_STORAGE_KEY = "arh-viewer-mode";
/** Pages kept decoded around the current one (continuous mode). */
const LOAD_WINDOW = 2;
/** Pages kept alive beyond the load window before their blob is revoked. */
const KEEP_EXTRA = 2;

/**
 * Secure document reader (spec §13/§15). Pages are rendered server-side
 * with the user's identity burned into the pixels; this component only
 * displays authorized page images and never touches the PDF.
 *
 * Reading modes: single page (prev/next) and continuous scroll with a
 * lazy load window — only pages near the viewport are rendered and kept
 * in memory; everything else is a lightweight placeholder.
 */
export function DocumentViewer({
  slug,
  title,
  pageCount,
}: DocumentViewerProps) {
  const total = Math.max(1, pageCount);

  const [mode, setMode] = useState<ReadMode>("single");
  const [page, setPage] = useState(1); // single-page mode cursor
  const [scrollPage, setScrollPage] = useState(1); // continuous-mode cursor
  const [zoom, setZoom] = useState(1);
  const [error, setError] = useState<ViewerError>(null);
  const [jumpOpen, setJumpOpen] = useState(false);

  // page image state (blob URLs), per page
  const [pageUrls, setPageUrls] = useState<Map<number, string>>(new Map());
  const [loadingPages, setLoadingPages] = useState<Set<number>>(new Set());
  const [pageErrors, setPageErrors] = useState<Map<number, string>>(new Map());

  const shellRef = useRef<HTMLDivElement>(null);
  const pageRefs = useRef<(HTMLDivElement | null)[]>([]);
  const pendingScrollTarget = useRef<number | null>(null);

  const displayPage = mode === "single" ? page : scrollPage;
  const progress = total > 1 ? (displayPage - 1) / (total - 1) : 1;

  // ── Viewing session bootstrap (single-flight; React strict mode
  //    mounts effects twice in dev, which would race page loads) ────
  const sessionPromise = useRef<Promise<void> | null>(null);

  const ensureSession = useCallback(() => {
    if (!sessionPromise.current) {
      sessionPromise.current = (async () => {
        const res = await fetch(`/api/viewer/${slug}/session`, { method: "POST" });
        if (res.status === 403) {
          setError({ kind: "denied", message: "You do not have access to this resource." });
        } else if (res.status === 404) {
          setError({ kind: "missing", message: "This resource no longer exists." });
        } else if (res.status === 410) {
          setError({ kind: "missing", message: "This resource is currently unavailable." });
        } else if (!res.ok) {
          setError({ kind: "session", message: "Could not start the viewer. Please try again." });
        }
      })().catch(() => {
        setError({ kind: "network", message: "Network error starting the viewer." });
        sessionPromise.current = null; // allow a real retry next time
      });
    }
    return sessionPromise.current;
  }, [slug]);

  useEffect(() => {
    void ensureSession();
  }, [ensureSession]);

  // ── Page loading: mint token → fetch image → blob URL ────────────
  // Deduped and memoized per page; lives outside state so parallel
  // callers share one request.
  const pageRequests = useRef<Map<number, Promise<string>>>(new Map());

  const getPageUrl = useCallback(
    (pageNumber: number): Promise<string> => {
      const existing = pageRequests.current.get(pageNumber);
      if (existing) return existing;

      const promise = (async () => {
        setLoadingPages((prev) => new Set(prev).add(pageNumber));
        setPageErrors((prev) => {
          if (!prev.has(pageNumber)) return prev;
          const next = new Map(prev);
          next.delete(pageNumber);
          return next;
        });
        try {
          const tokenRes = await fetch(`/api/viewer/${slug}/pages/${pageNumber}`, {
            method: "POST",
          });
          if (tokenRes.status === 428) {
            // Session window lapsed — restart once and redo the mint.
            sessionPromise.current = null;
            await ensureSession();
            const retry = await fetch(`/api/viewer/${slug}/pages/${pageNumber}`, {
              method: "POST",
            });
            if (!retry.ok) throw new Error("session");
            const { token: retryToken } = (await retry.json()) as { token: string };
            return await fetchImage(retryToken, pageNumber);
          }
          if (!tokenRes.ok) {
            const data = (await tokenRes.json().catch(() => ({}))) as { error?: string };
            if (tokenRes.status === 403) {
              setError({ kind: "denied", message: data.error ?? "You do not have access." });
            }
            throw new Error(data.error ?? "Could not open this page.");
          }
          const { token } = (await tokenRes.json()) as { token: string };
          return await fetchImage(token, pageNumber);
        } finally {
          setLoadingPages((prev) => {
            const next = new Set(prev);
            next.delete(pageNumber);
            return next;
          });
        }
      })().catch((err: Error) => {
        setPageErrors((prev) => new Map(prev).set(pageNumber, err.message));
        throw err;
      });

      pageRequests.current.set(pageNumber, promise);
      // On failure, drop the memo so a retry can run again.
      promise.catch(() => pageRequests.current.delete(pageNumber));
      return promise;
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [slug, ensureSession],
  );

  async function fetchImage(token: string, pageNumber: number): Promise<string> {
    const imgRes = await fetch(
      `/api/viewer/${slug}/pages/${pageNumber}?t=${encodeURIComponent(token)}`,
    );
    if (!imgRes.ok) {
      const data = (await imgRes.json().catch(() => ({}))) as { error?: string };
      if (imgRes.status === 403) {
        setError({ kind: "denied", message: data.error ?? "You do not have access." });
      }
      throw new Error(data.error ?? "Could not render this page.");
    }
    const blob = await imgRes.blob();
    const url = URL.createObjectURL(blob);
    setPageUrls((prev) => new Map(prev).set(pageNumber, url));
    return url;
  }

  const retryPage = useCallback(
    (pageNumber: number) => {
      pageRequests.current.delete(pageNumber);
      void getPageUrl(pageNumber);
    },
    [getPageUrl],
  );

  // ── Mode handling ────────────────────────────────────────────────
  // Restore persisted mode after mount (avoids SSR markup mismatch).
  useEffect(() => {
    const stored = window.localStorage.getItem(MODE_STORAGE_KEY);
    if (stored === "continuous") setMode("continuous");
  }, []);

  const switchMode = useCallback(
    (next: ReadMode) => {
      setMode((prev) => {
        if (prev === next) return prev;
        if (prev === "continuous") {
          // leaving continuous: land the single-page cursor on the
          // page the reader is actually looking at
          setPage(scrollPage);
        } else {
          // entering continuous: scroll to the current single page
          pendingScrollTarget.current = page;
        }
        window.localStorage.setItem(MODE_STORAGE_KEY, next);
        return next;
      });
    },
    [page, scrollPage],
  );

  // ── Continuous mode: scroll spy + lazy window ────────────────────
  const ensureWindowLoaded = useCallback(
    (center: number) => {
      for (let p = center - LOAD_WINDOW; p <= center + LOAD_WINDOW; p++) {
        if (p >= 1 && p <= total && !pageUrls.has(p)) {
          void getPageUrl(p).catch(() => undefined); // per-page error shown inline
        }
      }
    },
    [getPageUrl, pageUrls, total],
  );

  const evictDistant = useCallback((center: number) => {
    setPageUrls((prev) => {
      let changed = false;
      const next = new Map(prev);
      for (const p of next.keys()) {
        if (Math.abs(p - center) > LOAD_WINDOW + KEEP_EXTRA) {
          URL.revokeObjectURL(next.get(p)!);
          pageRequests.current.delete(p);
          next.delete(p);
          changed = true;
        }
      }
      return changed ? next : prev;
    });
  }, []);

  useEffect(() => {
    if (mode !== "continuous") return;
    const el = shellRef.current;
    if (!el) return;

    let ticking = false;
    const update = () => {
      ticking = false;
      const rect = el.getBoundingClientRect();
      const center = rect.top + rect.height / 2;

      let current = 1;
      for (let i = 0; i < total; i++) {
        const node = pageRefs.current[i];
        if (!node) continue;
        const r = node.getBoundingClientRect();
        if (r.top <= center && r.bottom >= center) {
          current = i + 1;
          break;
        }
        if (r.top > center) {
          current = i === 0 ? 1 : i; // first not-yet-reached page
          break;
        }
      }

      setScrollPage((prev) => (prev === current ? prev : current));
      ensureWindowLoaded(current);
      evictDistant(current);
    };

    const onScroll = () => {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    };

    el.addEventListener("scroll", onScroll, { passive: true });
    update(); // initial window
    return () => el.removeEventListener("scroll", onScroll);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode, total, ensureWindowLoaded, evictDistant]);

  // Scroll to the pending target after entering continuous mode.
  useLayoutEffect(() => {
    if (mode !== "continuous") return;
    const target = pendingScrollTarget.current;
    if (target == null) return;
    pendingScrollTarget.current = null;
    const node = pageRefs.current[target - 1];
    const el = shellRef.current;
    if (node && el) {
      el.scrollTo({ top: node.offsetTop - 12, behavior: "auto" });
    }
  }, [mode]);

  // Reveal the window for the initial page when placeholders mount.
  useEffect(() => {
    if (mode === "continuous") ensureWindowLoaded(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode]);

  // ── Single-page mode loading ─────────────────────────────────────
  useEffect(() => {
    if (mode !== "single") return;
    if (error?.kind === "denied" || error?.kind === "missing") return;
    void getPageUrl(page).catch(() => undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, mode]);

  // Revoke every blob URL on unmount.
  useEffect(() => {
    const requests = pageRequests;
    const urls = pageUrls;
    return () => {
      requests.current.clear();
      for (const url of urls.values()) URL.revokeObjectURL(url);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ── Keyboard navigation ──────────────────────────────────────────
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (jumpOpen && e.key === "Escape") {
        setJumpOpen(false);
        return;
      }
      if (e.key === "ArrowRight" || e.key === "PageDown") {
        if (mode === "single") setPage((p) => Math.min(total, p + 1));
        else scrollToPage(Math.min(total, scrollPage + 1));
      } else if (e.key === "ArrowLeft" || e.key === "PageUp") {
        if (mode === "single") setPage((p) => Math.max(1, p - 1));
        else scrollToPage(Math.max(1, scrollPage - 1));
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [total, mode, scrollPage, jumpOpen]);

  const scrollToPage = useCallback(
    (target: number) => {
      const node = pageRefs.current[target - 1];
      const el = shellRef.current;
      if (node && el) {
        el.scrollTo({ top: node.offsetTop - 12, behavior: "smooth" });
      }
      setScrollPage(target);
    },
    [],
  );

  // ── Pinch to zoom ────────────────────────────────────────────────
  const zoomRef = useRef(zoom);
  useEffect(() => {
    zoomRef.current = zoom;
  }, [zoom]);

  useEffect(() => {
    const el = shellRef.current;
    if (!el) return;
    let startDist = 0;
    let startZoom = 1;
    const onTouchStart = (e: TouchEvent) => {
      if (e.touches.length === 2) {
        startDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY,
        );
        startZoom = zoomRef.current;
      }
    };
    const onTouchMove = (e: TouchEvent) => {
      if (e.touches.length === 2 && startDist > 0) {
        const dist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY,
        );
        const next = Math.min(3, Math.max(0.6, startZoom * (dist / startDist)));
        setZoom(+next.toFixed(2));
      }
    };
    const onTouchEnd = () => {
      startDist = 0;
    };
    el.addEventListener("touchstart", onTouchStart, { passive: true });
    el.addEventListener("touchmove", onTouchMove, { passive: true });
    el.addEventListener("touchend", onTouchEnd);
    return () => {
      el.removeEventListener("touchstart", onTouchStart);
      el.removeEventListener("touchmove", onTouchMove);
      el.removeEventListener("touchend", onTouchEnd);
    };
  }, []);

  const goFullscreen = useCallback(() => {
    const el = shellRef.current;
    if (!el) return;
    if (document.fullscreenElement) void document.exitFullscreen();
    else void el.requestFullscreen?.().catch(() => undefined);
  }, []);

  // ── Error screen (global failures) ───────────────────────────────
  if (error && (error.kind === "denied" || error.kind === "missing" || error.kind === "session" || error.kind === "network")) {
    return (
      <div className="container-page flex min-h-[60vh] flex-col items-center justify-center py-20 text-center">
        <span className="grid h-16 w-16 place-items-center rounded-full bg-amber-50 ring-8 ring-amber-50/50">
          <svg className="h-8 w-8 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
          </svg>
        </span>
        <h1 className="mt-5 text-xl font-bold text-neutral-900">{error.message}</h1>
        <a href="/library" className="btn-primary mt-6">Back to My Library</a>
      </div>
    );
  }

  const renderPageContent = (pageNumber: number) => {
    const url = pageUrls.get(pageNumber);
    const loading = loadingPages.has(pageNumber);
    const pageError = pageErrors.get(pageNumber);

    if (url) {
      return (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={url}
          alt={`Page ${pageNumber} of ${total}`}
          className="block w-full select-none"
          draggable={false}
        />
      );
    }
    return (
      <div
        className="grid w-full place-items-center bg-white"
        style={{ aspectRatio: "1 / 1.414" }}
      >
        {loading ? (
          <div className="flex flex-col items-center gap-3 text-neutral-400">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-neutral-200 border-t-brand-600" />
            <p className="text-xs font-medium uppercase tracking-widest">
              Rendering page…
            </p>
          </div>
        ) : pageError ? (
          <div className="px-8 text-center">
            <p className="text-sm font-medium text-neutral-700">{pageError}</p>
            <button
              className="btn-secondary btn-sm mt-4"
              onClick={() => retryPage(pageNumber)}
            >
              Retry
            </button>
          </div>
        ) : (
          <p className="text-xs font-medium uppercase tracking-widest text-neutral-300">
            Page {pageNumber}
          </p>
        )}
      </div>
    );
  };

  return (
    <div className="flex h-[calc(100vh-4rem)] flex-col bg-neutral-100">
      {/* Top bar */}
      <div className="flex items-center justify-between gap-3 border-b border-neutral-200 bg-white px-4 py-2.5">
        <h1 className="truncate text-sm font-semibold text-neutral-800">{title}</h1>
        <div className="flex items-center gap-1.5">
          {/* Mode toggle */}
          <div className="flex overflow-hidden rounded-lg border border-neutral-200" role="group" aria-label="Reading mode">
            <button
              className={`px-2.5 py-1.5 text-xs font-semibold transition-colors ${
                mode === "single" ? "bg-brand-700 text-white" : "bg-white text-neutral-600 hover:bg-neutral-50"
              }`}
              onClick={() => switchMode("single")}
              aria-pressed={mode === "single"}
            >
              <svg className="mr-1 inline h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <rect x="5" y="3" width="14" height="18" rx="1" />
              </svg>
              Pages
            </button>
            <button
              className={`px-2.5 py-1.5 text-xs font-semibold transition-colors ${
                mode === "continuous" ? "bg-brand-700 text-white" : "bg-white text-neutral-600 hover:bg-neutral-50"
              }`}
              onClick={() => switchMode("continuous")}
              aria-pressed={mode === "continuous"}
            >
              <svg className="mr-1 inline h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M3 6h18" />
                <path d="M3 12h18" />
                <path d="M3 18h18" />
              </svg>
              Scroll
            </button>
          </div>

          <button
            className="btn-ghost btn-sm"
            onClick={() => setZoom((z) => Math.max(0.6, +(z - 0.2).toFixed(2)))}
            aria-label="Zoom out"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
              <path d="M5 12h14" />
            </svg>
          </button>
          <span className="w-12 text-center text-xs tabular-nums text-neutral-500">
            {Math.round(zoom * 100)}%
          </span>
          <button
            className="btn-ghost btn-sm"
            onClick={() => setZoom((z) => Math.min(3, +(z + 0.2).toFixed(2)))}
            aria-label="Zoom in"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
              <path d="M12 5v14" />
              <path d="M5 12h14" />
            </svg>
          </button>
          <button className="btn-ghost btn-sm" onClick={goFullscreen} aria-label="Fullscreen">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M8 3H5a2 2 0 0 0-2 2v3" />
              <path d="M21 8V5a2 2 0 0 0-2-2h-3" />
              <path d="M3 16v3a2 2 0 0 0 2 2h3" />
              <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
            </svg>
          </button>
        </div>
      </div>

      {/* Progress bar */}
      <div className="h-1 w-full bg-neutral-200">
        <div
          className="h-full bg-gradient-to-r from-brand-600 to-brand-500 transition-[width] duration-200"
          style={{ width: `${Math.round(progress * 100)}%` }}
        />
      </div>

      {/* Page canvas */}
      <div
        ref={shellRef}
        className="relative flex-1 overflow-auto p-4"
      >
        {mode === "single" ? (
          <div className="flex items-start justify-center">
            <div
              className="relative mx-auto w-full max-w-2xl overflow-hidden rounded-lg border border-neutral-300 bg-white shadow-card"
              style={{ transform: `scale(${zoom})`, transformOrigin: "top center" }}
            >
              {renderPageContent(page)}
            </div>
          </div>
        ) : (
          <div className="mx-auto flex w-full max-w-2xl flex-col gap-5">
            {Array.from({ length: total }, (_, i) => i + 1).map((p) => (
              <div
                key={p}
                ref={(node) => {
                  pageRefs.current[p - 1] = node;
                }}
                data-page={p}
              >
                <div
                  className="relative mx-auto overflow-hidden rounded-lg border border-neutral-300 bg-white shadow-card"
                  style={{ transform: `scale(${zoom})`, transformOrigin: "top center" }}
                >
                  {renderPageContent(p)}
                </div>
                <p className="mt-1 text-center text-[11px] tabular-nums text-neutral-400">
                  {p} / {total}
                </p>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Bottom controls */}
      <div className="relative flex items-center justify-center gap-4 border-t border-neutral-200 bg-white px-4 py-2.5">
        {mode === "single" && (
          <>
            <button
              className="btn-secondary btn-sm"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1 || loadingPages.has(page)}
            >
              ← Previous
            </button>
            <span className="text-sm tabular-nums text-neutral-600">
              Page {page} of {total}
            </span>
            <button
              className="btn-secondary btn-sm"
              onClick={() => setPage((p) => Math.min(total, p + 1))}
              disabled={page >= total || loadingPages.has(page)}
            >
              Next →
            </button>
          </>
        )}
        {mode === "continuous" && (
          <span className="text-sm text-neutral-600">
            Scrolling · <span className="tabular-nums font-medium text-neutral-800">{scrollPage}</span> of {total}
          </span>
        )}

        {/* Jump-to-page + progress pill */}
        <div className="absolute right-4 top-1/2 -translate-y-1/2">
          {jumpOpen ? (
            <div className="flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2 py-1 shadow-card">
              <input
                type="number"
                min={1}
                max={total}
                autoFocus
                defaultValue={displayPage}
                aria-label="Go to page"
                className="w-16 rounded-md border border-neutral-300 px-2 py-1 text-xs tabular-nums outline-none focus:border-brand-600"
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    const v = Math.min(total, Math.max(1, Number((e.target as HTMLInputElement).value) || 1));
                    if (mode === "single") setPage(v);
                    else scrollToPage(v);
                    setJumpOpen(false);
                  }
                  if (e.key === "Escape") setJumpOpen(false);
                }}
              />
              <button
                className="btn-primary btn-sm"
                onClick={(e) => {
                  const input = (e.currentTarget.previousElementSibling as HTMLInputElement);
                  const v = Math.min(total, Math.max(1, Number(input.value) || 1));
                  if (mode === "single") setPage(v);
                  else scrollToPage(v);
                  setJumpOpen(false);
                }}
              >
                Go
              </button>
            </div>
          ) : (
            <button
              className="flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold tabular-nums text-neutral-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700"
              onClick={() => setJumpOpen(true)}
              title="Jump to page"
            >
              <svg className="h-3.5 w-3.5 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M12 5v14" />
                <path d="m19 12-7 7-7-7" />
              </svg>
              {displayPage} / {total}
              <span className="text-neutral-400">·</span>
              <span className="text-brand-700">{Math.round(progress * 100)}%</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
