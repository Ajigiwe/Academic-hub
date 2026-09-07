import { prisma } from "@/lib/db";

/**
 * Grant an entitlement exactly once (spec §11: "prevent duplicate
 * entitlement creation"). Safe to call from both webhook and verify
 * paths concurrently — the unique (userId, resourceId) constraint is
 * the arbiter, and the winner is detected transactionally.
 *
 * Returns "created" if this call granted access, "existing" otherwise.
 */
export async function grantEntitlement(input: {
  userId: string;
  resourceId: string;
  orderId: string;
}): Promise<"created" | "existing"> {
  const { userId, resourceId, orderId } = input;

  try {
    const result = await prisma.$transaction(async (tx) => {
      const order = await tx.order.findUnique({
        where: { id: orderId },
        select: { status: true },
      });
      // Defense in depth: grants only flow from paid orders.
      if (!order || order.status !== "PAID") return null;

      return tx.entitlement.create({
        data: { userId, resourceId, orderId },
      });
    });

    return result ? "created" : "existing";
  } catch (err) {
    // P2002 = unique violation → concurrent grant already won.
    if (
      typeof err === "object" &&
      err !== null &&
      "code" in err &&
      (err as { code?: string }).code === "P2002"
    ) {
      return "existing";
    }
    throw err;
  }
}

/**
 * Bulk-model backfill: grants every PUBLISHED paper in a bundle to
 * everyone who has already PAID for that bundle.
 *
 * Papers uploaded after a purchase ("late uploads") have no entitlement
 * row at sale time, so they never reach past buyers' libraries. Run this
 * after any transition that publishes papers inside a sold bundle; it is
 * idempotent and only fills the gaps (unique (userId, resourceId)
 * constraint is the arbiter).
 *
 * Returns the number of entitlements created.
 */
export async function grantBundlePapersToPastBuyers(
  bundleId: string,
): Promise<number> {
  // Past buyers = users holding ≥1 active entitlement on a paper in this
  // bundle (every such row traces back to a paid order for the bundle).
  // Keep their EARLIEST bundle entitlement — that anchors the original order.
  const buyers = await prisma.entitlement.findMany({
    where: { resource: { bundleId }, status: "active" },
    distinct: ["userId"],
    orderBy: { purchasedAt: "asc" },
    select: {
      userId: true,
      orderId: true,
      order: { select: { paidAt: true, createdAt: true } },
    },
  });
  if (buyers.length === 0) return 0;

  const published = await prisma.resource.findMany({
    where: { bundleId, status: "PUBLISHED" },
    select: { id: true },
  });
  if (published.length === 0) return 0;

  const userIds = buyers.map((b) => b.userId);
  const resourceIds = published.map((r) => r.id);

  // Only fill gaps — never clobber an existing (possibly newer) row.
  const existing = await prisma.entitlement.findMany({
    where: {
      userId: { in: userIds },
      resourceId: { in: resourceIds },
      status: "active",
    },
    select: { userId: true, resourceId: true },
  });
  const covered = new Set(existing.map((e) => `${e.userId}:${e.resourceId}`));

  const data: { userId: string; resourceId: string; orderId: string; purchasedAt: Date }[] = [];
  for (const buyer of buyers) {
    for (const r of published) {
      if (covered.has(`${buyer.userId}:${r.id}`)) continue;
      data.push({
        userId: buyer.userId,
        resourceId: r.id,
        orderId: buyer.orderId,
        // Backdate to the original purchase so the library reads naturally.
        purchasedAt: buyer.order.paidAt ?? buyer.order.createdAt,
      });
    }
  }
  if (data.length === 0) return 0;

  await prisma.entitlement.createMany({ data, skipDuplicates: true });
  return data.length;
}
