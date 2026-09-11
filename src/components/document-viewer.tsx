"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  cachePageImage,
  isSavedOffline,
  offlineSupported,
  pageCachedOffline,
  writeOfflineMeta,
} from "@/lib/offline";

interface DocumentViewerProps {
  slug: string;
  title: string;
  pageCount: number;
}

type ViewerError = {
  kind: "denied" | "session" | "missing" | "network" | "render";
  message: string;
} | null;

/**
 * Secure document reader — one page per view. Pages are rendered
 * server-side with the user's identity burned into the pixels; this
 * component only displays authorized page images and never touches the PDF.
 */
export function DocumentViewer({
  slug,
  title,
  pageCount,
}: DocumentViewerProps) {
  const total = Math.max(1, pageCount);
  const [page, setPage] = useState(1);
  const [zoom, setZoom] = useState(1);
  const [error, setError] = useState<ViewerError>(null);
  const [jumpOpen, setJumpOpen] = useState(false);

  const [pageUrls, setPageUrls] = useState<Map<number, string>>(new Map());
  const [loadingPages, setLoadingPages] = useState<Set<number>>(new Set());
  const [pageErrors, setPageErrors] = useState<Map<number, string>>(new Map());

  const rootRef = useRef<HTMLDivElement>(null);
  const imgContainerRef = useRef<HTMLDivElement>(null);

  const progress = total > 1 ? (page - 1) / (total - 1) : 1;

  // ── Viewing session bootstrap ─────────────────────────────────────
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
        sessionPromise.current = null;
      });
    }
    return sessionPromise.current;
  }, [slug]);

  useEffect(() => {
    void ensureSession();
  }, [ensureSession]);

  // ── Page loading ──────────────────────────────────────────────────
  const pageRequests = useRef<Map<number, Promise<string>>>(new Map());

  const fetchPageResponse = useCallback(
    async (pageNumber: number): Promise<Response> => {
      const mint = async (): Promise<string> => {
        const tokenRes = await fetch(`/api/viewer/${slug}/pages/${pageNumber}`, {
          method: "POST",
        });
        if (tokenRes.status === 428) {
          sessionPromise.current = null;
          await ensureSession();
          const retry = await fetch(`/api/viewer/${slug}/pages/${pageNumber}`, {
            method: "POST",
          });
          if (!retry.ok) throw new Error("session");
          return ((await retry.json()) as { token: string }).token;
        }
        if (!tokenRes.ok) {
          const data = (await tokenRes.json().catch(() => ({}))) as { error?: string };
          if (tokenRes.status === 403) {
            setError({ kind: "denied", message: data.error ?? "You do not have access." });
          }
          throw new Error(data.error ?? "Could not open this page.");
        }
        return ((await tokenRes.json()) as { token: string }).token;
      };

      const token = await mint();
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
      return imgRes;
    },
    [slug, ensureSession],
  );

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
          const imgRes = await fetchPageResponse(pageNumber);
          const blob = await imgRes.blob();
          const url = URL.createObjectURL(blob);
          setPageUrls((prev) => new Map(prev).set(pageNumber, url));
          return url;
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
      promise.catch(() => pageRequests.current.delete(pageNumber));
      return promise;
    },
    [fetchPageResponse, ensureSession],
  );

  const retryPage = useCallback(
    (pageNumber: number) => {
      pageRequests.current.delete(pageNumber);
      void getPageUrl(pageNumber);
    },
    [getPageUrl],
  );

  // ── Load current page + prefetch neighbours ───────────────────────
  useEffect(() => {
    if (error?.kind === "denied" || error?.kind === "missing") return;
    void getPageUrl(page).catch(() => undefined);
    // Prefetch adjacent pages for snappy navigation
    if (page > 1) void getPageUrl(page - 1).catch(() => undefined);
    if (page < total) void getPageUrl(page + 1).catch(() => undefined);
  }, [page, total, getPageUrl, error]);

  // ── Save for offline ──────────────────────────────────────────────
  const [saveState, setSaveState] = useState<"idle" | "saving" | "saved">("idle");
  const [saveProgress, setSaveProgress] = useState(0);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);
  useEffect(() => { setMounted(true); }, []);

  useEffect(() => {
    if (!offlineSupported()) return;
    let cancelled = false;
    void isSavedOffline(slug).then((saved) => {
      if (!cancelled && saved) setSaveState("saved");
    });
    return () => { cancelled = true; };
  }, [slug]);

  async function saveForOffline() {
    if (saveState !== "idle" || !offlineSupported()) return;
    setSaveState("saving");
    setSaveProgress(0);
    setSaveError(null);
    try {
      for (let n = 1; n <= total; n++) {
        if (!(await pageCachedOffline(slug, n))) {
          const res = await fetchPageResponse(n);
          await cachePageImage(slug, n, res);
        }
        setSaveProgress(n);
      }
      await writeOfflineMeta({ slug, title, pageCount: total, savedAt: new Date().toISOString() });
      setSaveState("saved");
    } catch {
      setSaveState("idle");
      setSaveError("Could not finish saving. Check your connection and try again.");
    }
  }

  // ── Zoom ──────────────────────────────────────────────────────────
  const [displayZoom, setDisplayZoom] = useState(1);
  const zoomTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const applyZoom = useCallback((next: number) => {
    const clamped = Math.min(3, Math.max(0.5, next));
    setDisplayZoom(+clamped.toFixed(2));
  }, []);

  const zoomIn = useCallback(() => applyZoom(displayZoom + 0.25), [displayZoom, applyZoom]);
  const zoomOut = useCallback(() => applyZoom(displayZoom - 0.25), [displayZoom, applyZoom]);
  const resetZoom = useCallback(() => applyZoom(1), [applyZoom]);

  // Sync zoom state
  useEffect(() => { setZoom(displayZoom); }, [displayZoom]);

  // Wheel-to-zoom (Ctrl/Cmd + scroll)
  useEffect(() => {
    const el = imgContainerRef.current;
    if (!el) return;
    const onWheel = (e: WheelEvent) => {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault();
      const delta = e.deltaY > 0 ? -0.15 : 0.15;
      applyZoom(displayZoom + delta);
    };
    el.addEventListener("wheel", onWheel, { passive: false });
    return () => el.removeEventListener("wheel", onWheel);
  }, [displayZoom, applyZoom]);

  // Pinch-to-zoom
  useEffect(() => {
    const el = imgContainerRef.current;
    if (!el) return;
    let startDist = 0;
    let startZoom = 1;
    const onTouchStart = (e: TouchEvent) => {
      if (e.touches.length === 2) {
        startDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY,
        );
        startZoom = displayZoom;
      }
    };
    const onTouchMove = (e: TouchEvent) => {
      if (e.touches.length === 2 && startDist > 0) {
        e.preventDefault();
        const dist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY,
        );
        applyZoom(startZoom * (dist / startDist));
      }
    };
    const onTouchEnd = () => { startDist = 0; };
    el.addEventListener("touchstart", onTouchStart, { passive: true });
    el.addEventListener("touchmove", onTouchMove, { passive: false });
    el.addEventListener("touchend", onTouchEnd);
    return () => {
      el.removeEventListener("touchstart", onTouchStart);
      el.removeEventListener("touchmove", onTouchMove);
      el.removeEventListener("touchend", onTouchEnd);
    };
  }, [displayZoom, applyZoom]);

  // Cleanup zoom timer
  useEffect(() => {
    return () => { if (zoomTimer.current) clearTimeout(zoomTimer.current); };
  }, []);

  // ── Keyboard navigation ──────────────────────────────────────────
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (jumpOpen && e.key === "Escape") { setJumpOpen(false); return; }
      if (e.key === "ArrowRight" || e.key === "PageDown") {
        setPage((p) => Math.min(total, p + 1));
      } else if (e.key === "ArrowLeft" || e.key === "PageUp") {
        setPage((p) => Math.max(1, p - 1));
      } else if (e.key === "+" || e.key === "=") {
        applyZoom(displayZoom + 0.25);
      } else if (e.key === "-") {
        applyZoom(displayZoom - 0.25);
      } else if (e.key === "0") {
        applyZoom(1);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [total, jumpOpen, displayZoom, applyZoom]);

  // ── Screenshot & leak deterrence ──────────────────────────────────
  const [shielded, setShielded] = useState(false);
  const [deterrenceNote, setDeterrenceNote] = useState<string | null>(null);
  const noteTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const flashNote = useCallback((message: string) => {
    setDeterrenceNote(message);
    if (noteTimer.current) clearTimeout(noteTimer.current);
    noteTimer.current = setTimeout(() => setDeterrenceNote(null), 2600);
  }, []);

  useEffect(() => {
    const isMac = /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
    const isCaptureKey = (e: KeyboardEvent) =>
      e.key === "PrintScreen" ||
      (isMac && e.metaKey && e.shiftKey && ["3", "4", "5", "6"].includes(e.key));

    const shield = () => setShielded(true);
    const unshield = () => setShielded(false);
    const onVisibility = () => {
      if (document.visibilityState === "hidden") shield();
      else unshield();
    };

    const onKeyDown = (e: KeyboardEvent) => {
      if (!isCaptureKey(e)) return;
      shield();
      void navigator.clipboard
        ?.writeText("Screenshots are disabled — pages are watermarked to the reader's account.")
        .catch(() => undefined);
      flashNote("Screenshots are disabled — every page is watermarked to you.");
    };

    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("blur", shield);
    window.addEventListener("focus", unshield);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("visibilitychange", onVisibility);
      window.removeEventListener("blur", shield);
      window.removeEventListener("focus", unshield);
      document.removeEventListener("keydown", onKeyDown);
      if (noteTimer.current) clearTimeout(noteTimer.current);
    };
  }, [flashNote]);

  // Block Ctrl/Cmd+S and Ctrl/Cmd+P
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (!(e.ctrlKey || e.metaKey)) return;
      const key = e.key.toLowerCase();
      if (key === "s" || key === "p") {
        e.preventDefault();
        flashNote(
          key === "p"
            ? "Printing is disabled — pages are watermarked to you."
            : "Saving is disabled — pages are watermarked to you.",
        );
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [flashNote]);

  const goFullscreen = useCallback(() => {
    const el = rootRef.current;
    if (!el) return;
    if (document.fullscreenElement) void document.exitFullscreen();
    else void el.requestFullscreen?.().catch(() => undefined);
  }, []);

  // ── Swipe navigation (single finger, horizontal) ─────────────────
  const touchStart = useRef<{ x: number; y: number } | null>(null);
  useEffect(() => {
    const el = imgContainerRef.current;
    if (!el) return;
    const onStart = (e: TouchEvent) => {
      if (e.touches.length === 1) {
        touchStart.current = { x: e.touches[0].clientX, y: e.touches[0].clientY };
      }
    };
    const onEnd = (e: TouchEvent) => {
      if (!touchStart.current || e.changedTouches.length !== 1) return;
      const dx = e.changedTouches[0].clientX - touchStart.current.x;
      const dy = e.changedTouches[0].clientY - touchStart.current.y;
      touchStart.current = null;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        if (dx < 0 && page < total) setPage((p) => p + 1);
        else if (dx > 0 && page > 1) setPage((p) => p - 1);
      }
    };
    el.addEventListener("touchstart", onStart, { passive: true });
    el.addEventListener("touchend", onEnd, { passive: true });
    return () => {
      el.removeEventListener("touchstart", onStart);
      el.removeEventListener("touchend", onEnd);
    };
  }, [page, total]);

  // Revoke blob URLs on unmount
  useEffect(() => {
    const requests = pageRequests;
    const urls = pageUrls;
    return () => {
      requests.current.clear();
      for (const url of urls.values()) URL.revokeObjectURL(url);
    };
  }, []);

  // ── Error screen ──────────────────────────────────────────────────
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

  const url = pageUrls.get(page);
  const loading = loadingPages.has(page);
  const pageError = pageErrors.get(page);

  return (
    <div
      ref={rootRef}
      className="relative flex h-[100dvh] flex-col bg-neutral-100"
    >
      {/* Screenshot shield */}
      {shielded && (
        <div className="absolute inset-0 z-50 grid place-items-center bg-neutral-900 text-center print:hidden">
          <div className="px-6">
            <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-white/10">
              <svg className="h-7 w-7 text-neutral-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="m2 2 20 20" />
                <path d="M6.7 6.7C4.6 8.1 3 10 2 12c1.7 3.5 5.5 6 10 6 1.6 0 3.1-.3 4.5-.9" />
                <path d="M9.9 4.2A9.8 9.8 0 0 1 12 4c4.5 0 8.3 2.5 10 6a13.4 13.4 0 0 1-2.7 3.7" />
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
              </svg>
            </span>
            <p className="mt-4 text-sm font-semibold text-white">Content hidden</p>
            <p className="mt-1 text-xs text-neutral-400">This paper is watermarked to your account.</p>
          </div>
        </div>
      )}

      {/* Deterrence toast */}
      {deterrenceNote && (
        <div className="pointer-events-none absolute left-1/2 top-4 z-50 -translate-x-1/2 whitespace-nowrap rounded-full bg-neutral-900/90 px-4 py-2 text-xs font-semibold text-white shadow-lift print:hidden">
          {deterrenceNote}
        </div>
      )}

      {/* Top bar */}
      <div className="flex items-center justify-between gap-3 border-b border-neutral-200 bg-white px-3 py-2 print:hidden">
        <h1 className="truncate text-sm font-semibold text-neutral-800 min-w-0">{title}</h1>
        <div className="flex items-center gap-1 shrink-0">
          {mounted && offlineSupported() && (
            <button
              type="button"
              onClick={() => void saveForOffline()}
              disabled={saveState !== "idle"}
              title="Save for offline reading"
              className={`btn-sm ${saveState === "saved" ? "btn-secondary" : "btn-ghost"}`}
            >
              {saveState === "saving" ? (
                <span className="tabular-nums">{saveProgress}/{total}</span>
              ) : saveState === "saved" ? (
                <span className="text-brand-700">✓ Saved</span>
              ) : (
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                  <path d="M12 3v12" /><path d="m7 10 5 5 5-5" /><path d="M5 21h14" />
                </svg>
              )}
            </button>
          )}

          {/* Zoom controls */}
          <button className="btn-ghost btn-sm" onClick={zoomOut} aria-label="Zoom out">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
              <path d="M5 12h14" />
            </svg>
          </button>
          <button
            className="min-w-[44px] text-center text-xs tabular-nums text-neutral-500 hover:text-neutral-700"
            onClick={resetZoom}
            title="Reset zoom"
          >
            {Math.round(displayZoom * 100)}%
          </button>
          <button className="btn-ghost btn-sm" onClick={zoomIn} aria-label="Zoom in">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
              <path d="M12 5v14" /><path d="M5 12h14" />
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

      {/* Save error */}
      {saveError && (
        <div className="border-b border-red-200 bg-red-50 px-4 py-2 text-xs font-medium text-red-700 print:hidden">
          {saveError}
        </div>
      )}

      {/* Progress bar */}
      <div className="h-0.5 w-full bg-neutral-200 print:hidden">
        <div
          className="h-full bg-gradient-to-r from-brand-600 to-brand-500 transition-[width] duration-300 ease-out"
          style={{ width: `${Math.round(progress * 100)}%` }}
        />
      </div>

      {/* Page canvas */}
      <div
        ref={imgContainerRef}
        onContextMenu={(e) => e.preventDefault()}
        className="relative flex-1 overflow-hidden select-none [-webkit-touch-callout:none] print:hidden"
      >
        <div className="flex h-full items-start justify-center overflow-auto p-4">
          <div
            className="mx-auto w-full max-w-2xl origin-top transition-transform duration-200 ease-out"
            style={{ transform: `scale(${displayZoom})` }}
          >
            <div className="relative overflow-hidden rounded-lg border border-neutral-300 bg-white shadow-card">
              {url ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={url}
                  alt={`Page ${page} of ${total}`}
                  className="block w-full select-none"
                  draggable={false}
                />
              ) : (
                <div
                  className="grid w-full place-items-center bg-white"
                  style={{ aspectRatio: "1 / 1.414" }}
                >
                  {loading ? (
                    <div className="flex flex-col items-center gap-3 text-neutral-500">
                      <span className="h-8 w-8 animate-spin rounded-full border-2 border-neutral-200 border-t-brand-600" />
                      <p className="text-[11px] font-semibold uppercase tracking-[0.14em]">Rendering…</p>
                    </div>
                  ) : pageError ? (
                    <div className="px-8 text-center">
                      <p className="text-sm font-medium text-neutral-700">{pageError}</p>
                      <button className="btn-secondary btn-sm mt-4" onClick={() => retryPage(page)}>Retry</button>
                    </div>
                  ) : (
                    <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-400">Page {page}</p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Bottom controls */}
      <div className="relative flex items-center justify-between border-t border-neutral-200 bg-white px-3 py-2 print:hidden">
        <button
          className="btn-secondary btn-sm"
          onClick={() => setPage((p) => Math.max(1, p - 1))}
          disabled={page <= 1 || loadingPages.has(page)}
        >
          ← Prev
        </button>

        {/* Jump-to-page */}
        <div className="flex items-center">
          {jumpOpen ? (
            <div className="flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2 py-1 shadow-card">
              <input
                type="number"
                min={1}
                max={total}
                autoFocus
                defaultValue={page}
                aria-label="Go to page"
                className="w-14 rounded-md border border-neutral-300 px-2 py-1 text-xs tabular-nums outline-none focus:border-brand-600"
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    const v = Math.min(total, Math.max(1, Number((e.target as HTMLInputElement).value) || 1));
                    setPage(v);
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
                  setPage(v);
                  setJumpOpen(false);
                }}
              >
                Go
              </button>
            </div>
          ) : (
            <button
              className="text-sm tabular-nums text-neutral-600 hover:text-brand-700"
              onClick={() => setJumpOpen(true)}
            >
              {page} / {total}
            </button>
          )}
        </div>

        <button
          className="btn-secondary btn-sm"
          onClick={() => setPage((p) => Math.min(total, p + 1))}
          disabled={page >= total || loadingPages.has(page)}
        >
          Next →
        </button>
      </div>

      {/* Print guard */}
      <div className="hidden py-20 text-center print:block">
        <p className="text-lg font-semibold">Printing is not available</p>
        <p className="mx-auto mt-2 max-w-md text-sm text-neutral-600">
          This paper can only be read in the secure viewer, where every page is watermarked to your account.
        </p>
      </div>
    </div>
  );
}
