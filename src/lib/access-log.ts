import { prisma } from "@/lib/db";
import type { AccessAction } from "@prisma/client";

export interface RequestMeta {
  ipAddress?: string;
  userAgent?: string;
}

export function requestMetaFrom(req: Request): RequestMeta {
  return {
    ipAddress:
      req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ??
      undefined,
    userAgent: req.headers.get("user-agent") ?? undefined,
  };
}

/**
 * Fire-and-forget audit logging (spec §27). Never blocks or fails the
 * calling request — an audit write must not take the app down.
 */
export async function logAccess(entry: {
  userId?: string | null;
  resourceId?: string | null;
  action: AccessAction;
  detail?: string;
  meta?: RequestMeta;
}): Promise<void> {
  try {
    await prisma.accessLog.create({
      data: {
        userId: entry.userId ?? null,
        resourceId: entry.resourceId ?? null,
        action: entry.action,
        detail: entry.detail,
        ipAddress: entry.meta?.ipAddress,
        userAgent: entry.meta?.userAgent,
      },
    });
  } catch {
    // Logging must never break the user-facing request.
  }
}
