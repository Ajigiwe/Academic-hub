import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { renderPreviewPageCached } from "@/lib/pdf-render";

interface Params {
  params: Promise<{ slug: string }>;
}

/**
 * Public first-page preview (no login required). Deliberately different
 * from the secure viewer in every important way:
 *   - only page 1, ever, at reduced resolution;
 *   - a PREVIEW watermark burned in — no buyer identity, no license
 *     footer implying full access;
 *   - fully cacheable: identical PNG for every visitor.
 * The full secure chain (auth → entitlement → session → per-page token →
 * per-user watermark) remains the ONLY way to see pages 2+.
 */
export async function GET(
  _req: Request,
  { params }: Params,
) {
  const { slug } = await params;

  const resource = await prisma.resource.findFirst({
    where: {
      slug,
      status: "PUBLISHED",
      bundleId: { not: null }, // previews are for sellable papers only
    },
    select: {
      files: {
        where: { isCurrent: true },
        orderBy: { createdAt: "desc" },
        select: { storageKey: true },
        take: 1,
      },
    },
  });

  const storageKey = resource?.files[0]?.storageKey;
  if (!storageKey) {
    return NextResponse.json(
      { error: "Preview not available." },
      { status: 404 },
    );
  }

  const result = await renderPreviewPageCached(storageKey);
  if ("error" in result) {
    // Broken/encrypted uploads shouldn't leak internals; a flat 404
    // keeps the card UI's fallback simple.
    return NextResponse.json(
      { error: "Preview not available." },
      { status: 404 },
    );
  }

  return new NextResponse(new Uint8Array(result.png), {
    status: 200,
    headers: {
      "Content-Type": "image/png",
      "Cache-Control": "public, max-age=86400, stale-while-revalidate=604800",
      "X-Content-Type-Options": "nosniff",
    },
  });
}
