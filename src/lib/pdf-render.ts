import path from "node:path";
import { createRequire } from "node:module";
import { createCanvas, loadImage, type SKRSContext2D } from "@napi-rs/canvas";

/**
 * Server-side page pipeline (spec §13/§15): the client NEVER receives the
 * PDF. Each page request is re-rendered here into a PNG with the requesting
 * user's identity burned into the pixels. A DOM overlay would be trivially
 * bypassable; these marks are part of the rendered image itself. (§14:
 * cosmetics are not access control — the entitlement chain around this
 * module IS the access control.)
 *
 * Burned into every page:
 *   1. the page raster (rendered by pdf.js onto a server-side canvas)
 *   2. staggered diagonal identity tiles (name · id · order)
 *   3. a footer line with the licensee and order reference
 */

const require_ = createRequire(import.meta.url);

// ─────────────────────────────────────────────────────────────────
// pdf.js bootstrap — bundler-safe fake-worker setup
// ─────────────────────────────────────────────────────────────────
// We register the worker module directly (globalThis.pdfjsWorker) so
// pdf.js never needs to spawn a Worker or resolve workerSrc from disk.
// This is the pattern pdf.js documents for Node/bundler environments:
// require.resolve() gets replaced with a numeric module id by Turbopack
// in the standalone build, which previously crashed path.dirname().

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type PdfJsModule = any;
let pdfjsPromise: Promise<PdfJsModule> | null = null;

async function getPdfjs(): Promise<PdfJsModule> {
  if (!pdfjsPromise) {
    pdfjsPromise = Promise.all([
      import("pdfjs-dist/legacy/build/pdf.mjs"),
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      import("pdfjs-dist/legacy/build/pdf.worker.mjs" as any),
    ]).then(([pdfMod, workerMod]) => {
      const pdfjs = pdfMod as PdfJsModule;
      // Register the in-process worker handler — no workerSrc needed.
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (globalThis as any).pdfjsWorker = workerMod;
      // Standard-14 font metrics for PDFs that reference Helvetica etc.
      // Served over HTTP from public/ — Node's fetch rejects file:// URLs.
      const origin =
        process.env.APP_ORIGIN ?? `http://127.0.0.1:${process.env.PORT ?? 3000}`;
      pdfjs.GlobalWorkerOptions.workerSrc = ""; // forces the fake-worker path
      pdfjs.GlobalWorkerOptions.standardFontDataUrl = `${origin}/pdf-standard-fonts/`;
      return pdfjs;
    });
  }
  return pdfjsPromise;
}

async function loadDocument(data: Uint8Array) {
  const pdfjs = await getPdfjs();
  return pdfjs.getDocument({
    data: data.slice(), // pdf.js may transfer the buffer — always hand it a copy
    isEvalSupported: false,
    useSystemFonts: false,
    disableFontFace: true,
    standardFontDataUrl: pdfjs.GlobalWorkerOptions.standardFontDataUrl,
    password: "",
    // cMap + standardFontData for CJK / legacy fonts.
    cMapUrl: pdfjs.GlobalWorkerOptions.standardFontDataUrl,
    cMapPacked: true,
  }).promise;
}

// ─────────────────────────────────────────────────────────────────
// Public surface
// ─────────────────────────────────────────────────────────────────

export interface WatermarkIdentity {
  name: string;
  accountId: string;
  orderRef: string;
}

/** Marks for public previews — clearly not a licensed copy. */
export const PREVIEW_IDENTITY: WatermarkIdentity = {
  name: "PREVIEW",
  accountId: "SAMPLE",
  orderRef: "Buy the bundle to unlock all pages",
};

export interface RenderedPage {
  png: Buffer;
}

export interface PageProbeResult {
  pageCount: number;
  encrypted: boolean;
}

const RENDER_SCALE = 2; // ~144 dpi — crisp on phones and laptops

/**
 * Page-1 preview render: low resolution (half the licensed render's
 * linear size) with the PREVIEW marks instead of any buyer identity.
 * Nothing here is secret — the output is identical for every visitor.
 */
export async function renderPreviewPageCached(
  storageKey: string,
): Promise<{ png: Buffer } | { error: string; status: number }> {
  const cached = previewCache.get(storageKey);
  if (cached && cached.expiresAt > Date.now()) {
    return { png: cached.png };
  }

  const { getObjectBuffer } = await import("@/lib/storage");
  const data = await getObjectBuffer(storageKey);
  const rendered = await renderPageToPng(data, 1, PREVIEW_IDENTITY, {
    scale: PREVIEW_SCALE,
  });

  previewCache.set(storageKey, {
    png: rendered.png,
    expiresAt: Date.now() + PREVIEW_CACHE_TTL_MS,
  });
  return { png: rendered.png };
}

const PREVIEW_SCALE = 1; // ~72 dpi: readable but not crisp enough to sell
const previewCache = new Map<string, { png: Buffer; expiresAt: number }>();
const PREVIEW_CACHE_TTL_MS = 60 * 60 * 1000;
const PREVIEW_CACHE_MAX = 200;

export async function renderPageToPng(
  data: Uint8Array,
  pageNumber: number,
  identity: WatermarkIdentity,
  opts?: { scale?: number },
): Promise<RenderedPage> {
  const renderScale = Math.max(1, Math.min(opts?.scale ?? RENDER_SCALE, 4));
  const doc = await loadDocument(data);
  try {
    if (pageNumber < 1 || pageNumber > doc.numPages) {
      throw new Error(`Page ${pageNumber} out of range (1–${doc.numPages})`);
    }
    const page = await doc.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 1 });

    // Cap canvas size to avoid OOM / Skia crashes on huge pages.
    const maxDim = 4096;
    let scale = renderScale;
    while (
      Math.floor(viewport.width * scale) > maxDim ||
      Math.floor(viewport.height * scale) > maxDim
    ) {
      scale = Math.max(1, scale - 0.5);
    }

    // Try rendering; if it fails (e.g. unsupported canvas op for images),
    // retry at scale-1 and then scale-2 before giving up.
    for (const attemptScale of [scale, Math.max(1, scale - 1), 1]) {
      const canvas = createCanvas(
        Math.floor(viewport.width * attemptScale),
        Math.floor(viewport.height * attemptScale),
      );
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = "#ffffff";
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      const renderTask = page.render({
        canvasContext: ctx,
        viewport: page.getViewport({ scale: attemptScale }),
      });

      try {
        await renderTask.promise;
      } catch (renderErr) {
        console.error(
          `pdf.js render failed for page ${pageNumber} at scale ${attemptScale}:`,
          renderErr,
        );
        page.cleanup();
        continue; // retry at lower scale
      }

      page.cleanup();

      burnWatermark(
        canvas.getContext("2d"),
        canvas.width,
        canvas.height,
        identity,
      );
      const png = await canvas.encode("png");
      return { png };
    }

    throw new Error(`Page ${pageNumber} could not be rendered at any scale.`);
  } finally {
    await doc.destroy();
  }
}

/** Page count + encryption check without full rendering. */
export async function probePdf(data: Uint8Array): Promise<PageProbeResult> {
  try {
    const doc = await loadDocument(data);
    const pageCount = doc.numPages;
    await doc.destroy();
    return { pageCount, encrypted: false };
  } catch (err) {
    const name = (err as { name?: string }).name ?? "";
    if (name === "PasswordException" || name === "InvalidPasswordException") {
      return { pageCount: 0, encrypted: true };
    }
    throw err;
  }
}

// ─────────────────────────────────────────────────────────────────
// Watermark compositor — draws directly onto the page raster canvas,
// so the marks are flattened into the pixels in a single pass.
// ─────────────────────────────────────────────────────────────────

function burnWatermark(
  ctx: SKRSContext2D,
  widthPx: number,
  heightPx: number,
  identity: WatermarkIdentity,
): void {
  const wmText = `${identity.name} · ${identity.accountId} · ${identity.orderRef}`;
  const fontSize = Math.max(14, Math.round(widthPx * 0.015));

  ctx.save();

  // Staggered diagonal identity tiles.
  ctx.globalAlpha = 0.08;
  ctx.fillStyle = "#0d3324";
  ctx.font = `bold ${fontSize}px sans-serif`;
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";

  const textW = ctx.measureText(wmText).width;
  const cellW = Math.max(textW * 1.25, widthPx * 0.45);
  const cellH = cellW * 0.45;
  const angle = (-32 * Math.PI) / 180;
  const cols = Math.ceil(widthPx / cellW) + 1;
  const rows = Math.ceil(heightPx / cellH) + 1;

  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const cx = c * cellW + (r % 2 === 0 ? 0 : cellW / 2);
      const cy = r * cellH + cellH / 2;
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(angle);
      ctx.fillText(wmText, 0, 0);
      ctx.restore();
    }
  }

  // Footer license line.
  ctx.globalAlpha = 0.65;
  ctx.fillStyle = "#4b5553";
  ctx.font = `bold ${Math.max(11, Math.round(widthPx * 0.011))}px sans-serif`;
  ctx.textAlign = "center";
  ctx.textBaseline = "alphabetic";
  ctx.fillText(
    `Licensed to ${identity.name} (${identity.accountId}) · Order ${identity.orderRef} · Academic Resource Hub`,
    widthPx / 2,
    heightPx - 14,
  );

  ctx.restore();
}

// ─────────────────────────────────────────────────────────────────
// Render cache — identical (user, page) renders repeat within a session
// ─────────────────────────────────────────────────────────────────

interface CacheEntry {
  png: Buffer;
  expiresAt: number;
}

const pageCache = new Map<string, CacheEntry>();
const PAGE_CACHE_TTL_MS = 5 * 60 * 1000;
const PAGE_CACHE_MAX = 300;

export async function renderPageCached(
  cacheKey: string,
  loadPdfBytes: () => Promise<Uint8Array>,
  pageNumber: number,
  identity: WatermarkIdentity,
): Promise<{ png: Buffer } | { error: string; status: number }> {
  const cached = pageCache.get(cacheKey);
  if (cached && cached.expiresAt > Date.now()) {
    return { png: cached.png };
  }

  const data = await loadPdfBytes();
  let result: RenderedPage;
  try {
    result = await renderPageToPng(data, pageNumber, identity);
  } catch (err) {
    const name = (err as { name?: string }).name ?? "";
    if (name === "PasswordException" || name === "InvalidPasswordException") {
      return { error: "This document is password-protected.", status: 422 };
    }
    if (name === "InvalidPDFException") {
      return { error: "This file could not be rendered as a PDF.", status: 422 };
    }
    // Catch rendering failures (missing image codecs, unsupported features,
    // canvas errors) instead of letting them bubble up as 500s.
    console.error(`Render failed for page ${pageNumber}:`, err);
    return {
      error: `Page ${pageNumber} could not be rendered. ${
        err instanceof Error ? err.message : ""
      }`.trim(),
      status: 422,
    };
  }

  if (pageCache.size >= PAGE_CACHE_MAX) {
    const oldest = pageCache.keys().next().value;
    if (oldest) pageCache.delete(oldest);
  }
  pageCache.set(cacheKey, {
    png: result.png,
    expiresAt: Date.now() + PAGE_CACHE_TTL_MS,
  });

  return { png: result.png };
}
