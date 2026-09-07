import { prisma } from "@/lib/db";
import { requireUser } from "@/lib/auth";
import { hasEntitlement } from "@/lib/entitlements";
import { logAccess, requestMetaFrom } from "@/lib/access-log";
import { NextResponse } from "next/server";

interface Params {
  params: Promise<{ slug: string }>;
}

/**
 * Creates a viewing session for an entitled user (spec §26).
 * This is the ONLY door into the viewer, and it runs the full
 * authorization chain: authenticated → entitled → session created.
 */
export async function POST(req: Request, { params }: Params) {
  const { user, error } = await requireUser();
  if (error) return error;

  const { slug } = await params;
  const meta = requestMetaFrom(req);

  const resource = await prisma.resource.findUnique({
    where: { slug },
    select: { id: true, title: true, status: true },
  });

  if (!resource) {
    await logAccess({
      userId: user.id,
      action: "ACCESS_DENIED",
      detail: `Viewer session for unknown resource slug: ${slug}`,
      meta,
    });
    return NextResponse.json({ error: "Resource not found." }, { status: 404 });
  }

  const entitled = await hasEntitlement(user, resource.id);
  if (!entitled) {
    await logAccess({
      userId: user.id,
      resourceId: resource.id,
      action: "ACCESS_DENIED",
      detail: "Viewer session without entitlement.",
      meta,
    });
    return NextResponse.json(
      { error: "You do not have access to this resource." },
      { status: 403 },
    );
  }

  if (resource.status === "ARCHIVED") {
    return NextResponse.json(
      { error: "This resource is currently unavailable." },
      { status: 410 },
    );
  }

  // Entitlement row exists for purchases; admins get role-granted access
  // (hasEntitlement above) and legitimately have no purchase row.
  const entitlement = await prisma.entitlement.findFirst({
    where: { userId: user.id, resourceId: resource.id, status: "active" },
    select: { id: true },
  });

  const session = await prisma.viewingSession.create({
    data: {
      userId: user.id,
      resourceId: resource.id,
      entitlementId: entitlement?.id,
      ipAddress: meta.ipAddress,
      userAgent: meta.userAgent,
    },
    select: { id: true, startedAt: true },
  });

  await logAccess({
    userId: user.id,
    resourceId: resource.id,
    action: "STARTED_VIEWER",
    detail: `Viewing session ${session.id}`,
    meta,
  });

  // Best-effort "recently viewed" upsert (spec §12).
  await prisma.recentlyViewed
    .upsert({
      where: {
        userId_resourceId: { userId: user.id, resourceId: resource.id },
      },
      create: { userId: user.id, resourceId: resource.id },
      update: { viewedAt: new Date() },
    })
    .catch(() => undefined);

  return NextResponse.json({
    sessionId: session.id,
    startedAt: session.startedAt,
  });
}
