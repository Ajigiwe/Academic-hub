import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { requireUser } from "@/lib/auth";
import { hasEntitlement } from "@/lib/entitlements";
import { logAccess, requestMetaFrom } from "@/lib/access-log";
import { verifyPageToken, mintPageToken, PAGE_TOKEN_TTL_SECONDS } from "@/lib/page-tokens";
import { renderPageCached } from "@/lib/pdf-render";

interface Params {
  params: Promise<{ slug: string; page: string }>;
}

const SESSION_FRESH_WINDOW_MS = 10 * 60 * 1000; // re-auth chain every 10 min

function parsePage(pageParam: string): number | null {
  const n = Number(pageParam);
  if (!Number.isInteger(n) || n < 1 || n > 500) return null;
  return n;
}

/**
 * Serves ONE watermarked page image (spec §13/§26). The full chain runs on
 * every request: authenticated → entitled → current file → valid page
 * token → live viewing session. The PDF itself never leaves the server.
 */
export async function GET(req: Request, { params }: Params) {
  const { user, error } = await requireUser();
  if (error) return error;

  const { slug, page: pageParam } = await params;
  const meta = requestMetaFrom(req);
  const url = new URL(req.url);
  const token = url.searchParams.get("t");

  const pageNumber = parsePage(pageParam);
  if (!pageNumber) {
    return NextResponse.json({ error: "Invalid page." }, { status: 400 });
  }

  const resource = await prisma.resource.findUnique({
    where: { slug },
    select: { id: true, status: true },
  });
  if (!resource) {
    return NextResponse.json({ error: "Not found." }, { status: 404 });
  }
  if (resource.status === "ARCHIVED") {
    return NextResponse.json({ error: "Unavailable." }, { status: 410 });
  }

  const entitled = await hasEntitlement(user, resource.id);
  if (!entitled) {
    await logAccess({
      userId: user.id,
      resourceId: resource.id,
      action: "ACCESS_DENIED",
      detail: `Page ${pageNumber} without entitlement.`,
      meta,
    });
    return NextResponse.json({ error: "No access." }, { status: 403 });
  }

  const file = await prisma.resourceFile.findFirst({
    where: { resourceId: resource.id, isCurrent: true },
    orderBy: { createdAt: "desc" },
    select: { id: true, storageKey: true },
  });
  if (!file) {
    return NextResponse.json(
      { error: "No file attached to this resource yet." },
      { status: 404 },
    );
  }

  // Token first: HMAC + expiry + binding to user/file/page.
  const payload = verifyPageToken(token);
  if (
    !payload ||
    payload.userId !== user.id ||
    payload.fileId !== file.id ||
    payload.page !== pageNumber
  ) {
    return NextResponse.json(
      { error: "Invalid or expired page token." },
      { status: 403 },
    );
  }

  // Session: anchored to the token's session id (not "latest") so
  // concurrent sessions never invalidate each other's tokens.
  const session = await prisma.viewingSession.findFirst({
    where: {
      id: payload.sessionId,
      userId: user.id,
      resourceId: resource.id,
      endedAt: null,
      startedAt: { gte: new Date(Date.now() - SESSION_FRESH_WINDOW_MS) },
    },
    select: { id: true },
  });
  if (!session) {
    return NextResponse.json(
      { error: "Viewer session expired. Reload the page." },
      { status: 428 },
    );
  }

  const identity = {
    name: `${user.firstName} ${user.lastName}`,
    accountId: user.id.slice(-6).toUpperCase(),
    // Bulk model: the purchase is a BUNDLE; find the paid order whose
    // bundle contains this paper.
    orderRef: await prisma.orderItem
      .findFirst({
        where: {
          bundle: { resources: { some: { id: resource.id } } },
          order: { userId: user.id, status: "PAID" },
        },
        orderBy: { order: { paidAt: "desc" } },
        select: { order: { select: { reference: true } } },
      })
      .then((oi) => oi?.order.reference ?? "—"),
  };

  const result = await renderPageCached(
    `${identity.accountId}:${file.id}:${pageNumber}`,
    async () => {
      const { getObjectBuffer } = await import("@/lib/storage");
      return getObjectBuffer(file.storageKey);
    },
    pageNumber,
    identity,
  );

  if ("error" in result) {
    return NextResponse.json({ error: result.error }, { status: result.status });
  }

  await logAccess({
    userId: user.id,
    resourceId: resource.id,
    action: "PAGE_VIEWED",
    detail: `Page ${pageNumber} · session ${session.id}`,
    meta,
  });

  return new NextResponse(result.png as unknown as BodyInit, {
    status: 200,
    headers: {
      "Content-Type": "image/png",
      "Cache-Control": "no-store, must-revalidate",
      "Content-Disposition": "inline",
      "X-Content-Type-Options": "nosniff",
    },
  });
}

/** Mints a fresh token for one page (session must be live). */
export async function POST(req: Request, { params }: Params) {
  const { user, error } = await requireUser();
  if (error) return error;

  const { slug, page: pageParam } = await params;
  const pageNumber = parsePage(pageParam);
  if (!pageNumber) {
    return NextResponse.json({ error: "Invalid page." }, { status: 400 });
  }

  const resource = await prisma.resource.findUnique({
    where: { slug },
    select: { id: true, status: true },
  });
  if (!resource || resource.status === "ARCHIVED") {
    return NextResponse.json({ error: "Not found." }, { status: 404 });
  }

  const entitled = await hasEntitlement(user, resource.id);
  if (!entitled) {
    return NextResponse.json({ error: "No access." }, { status: 403 });
  }

  const file = await prisma.resourceFile.findFirst({
    where: { resourceId: resource.id, isCurrent: true },
    orderBy: { createdAt: "desc" },
    select: { id: true },
  });
  if (!file) {
    return NextResponse.json({ error: "No file attached." }, { status: 404 });
  }

  const session = await prisma.viewingSession.findFirst({
    where: {
      userId: user.id,
      resourceId: resource.id,
      endedAt: null,
      startedAt: { gte: new Date(Date.now() - SESSION_FRESH_WINDOW_MS) },
    },
    orderBy: { startedAt: "desc" },
    select: { id: true },
  });
  if (!session) {
    return NextResponse.json(
      { error: "Viewer session expired. Reload the page." },
      { status: 428 },
    );
  }

  const token = mintPageToken({
    userId: user.id,
    fileId: file.id,
    page: pageNumber,
    sessionId: session.id,
  });

  return NextResponse.json(
    { token, expiresIn: PAGE_TOKEN_TTL_SECONDS },
    { headers: { "Cache-Control": "no-store" } },
  );
}
