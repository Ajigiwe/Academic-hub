import { prisma } from "@/lib/db";
import type { SessionUser } from "@/lib/auth";

/**
 * Entitlement engine (spec §26) — the single source of truth for
 * "may this user open this resource?". Every reader path goes through
 * here; no page or route may make its own entitlement decision.
 *
 * Bulk model: a paid bundle unlocks every paper inside it. Entitlements
 * are granted per-resource at purchase time, AND the ownership check
 * falls back to "the user paid for a bundle containing this resource" —
 * so papers added to a bundle after the purchase are unlocked too.
 */
export async function hasEntitlement(
  user: SessionUser,
  resourceId: string,
): Promise<boolean> {
  if (user.role === "ADMIN") return true;

  const entitlement = await prisma.entitlement.findFirst({
    where: {
      userId: user.id,
      resourceId,
      status: "active",
    },
    select: { id: true },
  });
  if (entitlement) return true;

  // Bundle-ownership fallback: user bought the bundle this paper belongs to.
  const resource = await prisma.resource.findUnique({
    where: { id: resourceId },
    select: { bundleId: true },
  });
  if (!resource?.bundleId) return false;

  const viaBundle = await prisma.entitlement.findFirst({
    where: {
      userId: user.id,
      status: "active",
      order: { items: { some: { bundleId: resource.bundleId } } },
    },
    select: { id: true },
  });
  return viaBundle !== null;
}